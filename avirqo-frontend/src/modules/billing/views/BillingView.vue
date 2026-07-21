<template>
  <AppLayout>
    <div class="billing-page">
      <div class="billing-header">
        <div>
          <h1>Billing</h1>
          <p>Manage Proforma Invoices, payments, Tax Invoices, Credit/Debit Notes and reports.</p>
        </div>
        <button v-if="tab === 'proformas'" class="avq-btn-primary" @click="openPiModal()">+ New PI</button>
        <button v-if="tab === 'payments'" class="avq-btn-primary" @click="openPaymentModal()">+ Capture Payment</button>
      </div>

      <div class="billing-tabs">
        <RouterLink
          v-for="item in tabs"
          :key="item.key"
          class="billing-tab"
          :class="{ active: tab === item.key }"
          :to="item.path"
        >
          {{ item.label }}
        </RouterLink>
      </div>

      <section v-if="tab === 'proformas'" class="billing-card">
        <div class="billing-toolbar">
          <input v-model="filters.search" class="avq-input" placeholder="Search PI/customer…" @input="loadProformas" />
          <button class="avq-btn-ghost" @click="loadProformas">Refresh</button>
        </div>
        <table class="billing-table">
          <thead><tr><th>PI No.</th><th>Customer</th><th>Status</th><th>Total</th><th>Paid</th><th>Delivered</th><th>Available</th><th>Actions</th></tr></thead>
          <tbody>
            <tr v-if="!proformas.length"><td colspan="8" class="empty">No Proforma Invoices found.</td></tr>
            <tr v-for="pi in proformas" :key="pi.id">
              <td>{{ pi.pi_number || pi.draft_number }}</td>
              <td>{{ pi.customer?.company_name }}</td>
              <td><span class="badge">{{ pi.status }}</span></td>
              <td class="num">₹{{ fmt(pi.total_amount) }}</td>
              <td class="num">₹{{ fmt(pi.paid_amount) }}</td>
              <td class="num">₹{{ fmt(pi.delivered_amount) }}</td>
              <td class="num">₹{{ fmt(Number(pi.balance_added_amount || 0) - Number(pi.delivered_amount || 0)) }}</td>
              <td>
                <div class="actions">
                  <button class="avq-btn-sm" :disabled="pi.status !== 'draft'" @click="openPiModal(pi)">Edit</button>
                  <button class="avq-btn-sm" :disabled="pi.status !== 'draft'" @click="openFinalizePiModal(pi)">Finalize</button>
                  <button class="avq-btn-sm" @click="openPdfPreview('proforma_invoice', pi.id, pi.pi_number || pi.draft_number)">PDF</button>
                  <button class="avq-btn-sm" @click="openEmailModal('proforma_invoice', pi)">Email internal</button>
                  <button class="avq-btn-sm" :disabled="Number(pi.delivered_amount || 0) > 0 || pi.status === 'cancelled'" @click="openCancelPiModal(pi)">Cancel</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <section v-if="tab === 'payments'" class="billing-card">
        <div class="billing-toolbar">
          <input v-model="filters.paymentsSearch" class="avq-input" placeholder="Search customer/payment/PI…" @input="loadPayments" />
          <button class="avq-btn-ghost" @click="loadPayments">Refresh</button>
        </div>
        <table class="billing-table">
          <thead><tr><th>Payment No.</th><th>Customer</th><th>PI</th><th>Date</th><th>Amount</th><th>Balance Added</th><th>Credit Note</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <tr v-if="!payments.length"><td colspan="9" class="empty">No payments found.</td></tr>
            <tr v-for="payment in payments" :key="payment.id">
              <td>{{ payment.payment_number }}</td>
              <td>{{ payment.customer?.company_name }}</td>
              <td>{{ payment.proforma_invoice?.pi_number }}</td>
              <td>{{ formatFriendlyDate(payment.payment_date) }}</td>
              <td class="num">₹{{ fmt(payment.amount) }}</td>
              <td class="num">₹{{ fmt(payment.balance_added_amount) }}</td>
              <td class="num">₹{{ fmt(payment.credit_note_amount) }}</td>
              <td><span class="badge">{{ payment.status }}</span></td>
              <td><button class="avq-btn-sm" :disabled="payment.status === 'invalid'" @click="invalidatePayment(payment)">Mark invalid</button></td>
            </tr>
          </tbody>
        </table>
      </section>

      <section v-if="tab === 'tax'" class="billing-card">
        <div class="billing-toolbar">
          <input v-model="filters.taxSearch" class="avq-input" placeholder="Search customer/invoice/PI…" @input="loadTaxInvoices" />
          <button class="avq-btn-ghost" @click="loadTaxInvoices">Refresh</button>
        </div>
        <table class="billing-table">
          <thead><tr><th>Invoice No.</th><th>Draft No.</th><th>Customer</th><th>PI</th><th>Status</th><th>Total</th><th>Actions</th></tr></thead>
          <tbody>
            <tr v-if="!taxInvoices.length"><td colspan="7" class="empty">No Tax Invoices found.</td></tr>
            <tr v-for="invoice in taxInvoices" :key="invoice.id">
              <td>{{ invoice.invoice_number || '—' }}</td>
              <td>{{ invoice.draft_number }}</td>
              <td>{{ invoice.customer?.company_name }}</td>
              <td>{{ invoice.proforma_invoice?.pi_number || '—' }}</td>
              <td><span class="badge">{{ invoice.status }}</span></td>
              <td class="num">₹{{ fmt(invoice.total_amount) }}</td>
              <td><button class="avq-btn-sm" @click="openPdfPreview('tax_invoice', invoice.id, invoice.invoice_number || invoice.draft_number)">PDF</button></td>
            </tr>
          </tbody>
        </table>
      </section>

      <section v-if="tab === 'notes'" class="billing-card">
        <div class="billing-toolbar">
          <input v-model="filters.notesSearch" class="avq-input" placeholder="Search customer/note/PI…" @input="loadNotes" />
          <button class="avq-btn-ghost" @click="loadNotes">Refresh</button>
        </div>
        <table class="billing-table">
          <thead><tr><th>No.</th><th>Type</th><th>Customer</th><th>PI</th><th>Amount</th><th>Remaining</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <tr v-if="!notes.length"><td colspan="8" class="empty">No Credit/Debit Notes found.</td></tr>
            <tr v-for="note in notes" :key="note.id">
              <td>{{ note.note_number || note.draft_number }}</td>
              <td>{{ note.type }}</td>
              <td>{{ note.customer?.company_name }}</td>
              <td>{{ note.proforma_invoice?.pi_number || '—' }}</td>
              <td class="num">₹{{ fmt(note.amount) }}</td>
              <td class="num">₹{{ fmt(note.remaining_amount) }}</td>
              <td><span class="badge">{{ note.status }}</span></td>
              <td>
                <div class="actions">
                  <button class="avq-btn-sm" @click="openPdfPreview(note.type === 'credit' ? 'credit_note' : 'debit_note', note.id, note.note_number || note.draft_number)">PDF</button>
                  <button
                    v-if="canApplyCreditNote(note)"
                    class="avq-btn-sm"
                    @click="openCreditApplyModal(note)"
                  >
                    Apply to PI Balance
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <section v-if="tab === 'approvers'" class="billing-card">
        <h2>OTP approver configuration</h2>
        <p class="muted">Configure approver email IDs for Global Margin, Campaign Changes, Order OTP and Billing Control.</p>
        <div v-for="group in approvers" :key="group.group_key" class="approver-row">
          <div>
            <strong>{{ group.label }}</strong>
            <div class="muted">{{ group.group_key }}</div>
          </div>
          <div>
            <input
              v-model="group.emailText"
              class="avq-input"
              :class="{ 'is-invalid': approverErrors[group.group_key] }"
              placeholder="email1@example.com, email2@example.com"
            />
            <small v-if="approverErrors[group.group_key]" class="field-error">{{ approverErrors[group.group_key] }}</small>
          </div>
        </div>
        <button class="avq-btn-primary" @click="saveApprovers">Save approvers</button>
      </section>

      <section v-if="tab === 'reports'" class="billing-card coming-soon">
        <h2>Reports</h2>
        <p>Coming soon.</p>
      </section>

      <div v-if="showPiModal" class="avq-modal-overlay" @click.self="showPiModal = false">
        <form class="avq-modal avq-modal-lg" @submit.prevent="savePi">
          <h3>{{ piForm.id ? 'Edit Proforma Invoice' : 'New Proforma Invoice' }}</h3>
          <div v-if="piErrors.general.length" class="form-error-summary">
            <p v-for="error in piErrors.general" :key="error">{{ error }}</p>
          </div>
          <div class="form-grid">
            <div>
              <label>Customer</label>
              <div class="select2-combobox">
                <input
                  v-model="piForm.customerSearch"
                  class="avq-input select2-combobox__input"
                  :class="{ 'is-invalid': piErrors.fields.customer_id }"
                  type="text"
                  required
                  placeholder="Search and select customer"
                  autocomplete="off"
                  @focus="openCustomerDropdown($event)"
                  @input="onCustomerSearchInput"
                  @blur="closeCustomerDropdownSoon"
                />
                <div v-if="showCustomerDropdown" class="select2-combobox__menu">
                  <button
                    v-for="customer in filteredCustomers"
                    :key="customer.id"
                    type="button"
                    class="select2-combobox__option"
                    @mousedown.prevent="selectCustomer(customer)"
                  >
                    <span>
                      <strong>{{ customer.company_name }}</strong>
                      <small>{{ customer.location || 'No location added' }}</small>
                    </span>
                    <em>Select</em>
                  </button>
                  <p v-if="!filteredCustomers.length" class="select2-combobox__empty">No customers found.</p>
                </div>
              </div>
              <small v-if="piErrors.fields.customer_id" class="field-error">{{ piErrors.fields.customer_id }}</small>
            </div>
            <div>
              <label>Issue date</label>
              <input
                v-model="piForm.issue_date"
                type="date"
                class="avq-input"
                :class="{ 'is-invalid': piErrors.fields.issue_date }"
                :min="piIssueDateMin"
                :max="todayDate"
                :disabled="Boolean(piForm.id)"
                required
              />
              <small v-if="piErrors.fields.issue_date" class="field-error">{{ piErrors.fields.issue_date }}</small>
              <small v-else-if="piForm.id" class="field-hint">Issue date cannot be changed after PI is created.</small>
            </div>
            <div><label>Valid until</label><input v-model="piForm.valid_until" type="date" class="avq-input" /></div>
          </div>
          <div class="discount-mode-panel">
            <label>Discount type</label>
            <div class="discount-mode-options">
              <label>
                <input type="radio" value="campaign" v-model="piForm.discount_type" @change="applyDiscountMode" />
                Campaign Discount
              </label>
              <label>
                <input type="radio" value="invoice" v-model="piForm.discount_type" @change="applyDiscountMode" />
                Invoice Discount
              </label>
            </div>
            <p v-if="piForm.discount_type === 'campaign'" class="muted">
              {{ selectedCampaign ? `Using campaign: ${selectedCampaign.name}` : 'No active campaign mapped to this customer. Discounts will be 0%.' }}
            </p>
            <div v-else class="invoice-discount-field">
              <label>Discount / Service Charge (%)</label>
              <input
                v-model.number="piForm.invoice_discount_percentage"
                class="avq-input"
                type="number"
                step=".01"
                placeholder="0"
                @focus="$event.target.select()"
              />
              <small>Positive value reduces the PI total. Negative value adds a service charge.</small>
            </div>
          </div>
          <div v-if="piForm.customer_id" class="credit-note-panel">
            <div class="credit-note-panel__head">
              <div>
                <h4>Credit Note adjustment</h4>
                <p class="muted">Reserve available customer credit notes now. Balance is added only when this PI is finalized.</p>
              </div>
              <button type="button" class="avq-btn-sm" @click="loadCustomerCreditNotes">Refresh</button>
            </div>
            <div v-if="!availableCreditNotes.length" class="credit-note-empty">No active credit notes available for this customer.</div>
            <div v-else class="credit-note-list">
              <label v-for="note in availableCreditNotes" :key="note.id" class="credit-note-row">
                <input
                  type="checkbox"
                  :checked="isCreditNoteSelected(note.id)"
                  @change="toggleCreditNote(note, $event.target.checked)"
                />
                <span>
                  <strong>{{ note.note_number || note.draft_number }}</strong>
                  <small>Available ₹{{ fmt(note.available_amount) }}<template v-if="note.source_pi_number"> · From {{ note.source_pi_number }}</template></small>
                </span>
                <input
                  v-if="isCreditNoteSelected(note.id)"
                  v-model.number="selectedCreditNote(note.id).amount"
                  class="avq-input credit-note-amount"
                  type="number"
                  min="0.01"
                  step="0.01"
                  :max="note.available_amount"
                  @focus="$event.target.select()"
                />
              </label>
            </div>
          </div>
          <h4>Items</h4>
          <div class="pi-items-scroll">
            <div class="pi-items-grid">
              <div class="item-header-row">
                <span>Brand Name</span>
                <span>Denomination</span>
                <span>Qty</span>
                <span>Adjustment %</span>
                <span>GST %</span>
                <span>Action</span>
              </div>
              <div v-for="(item, idx) in piForm.items" :key="idx" class="item-row">
                <div class="select2-combobox">
                  <input
                    v-model="item.productSearch"
                    class="avq-input select2-combobox__input"
                    :class="{ 'is-invalid': itemError(idx, 'product_id') }"
                    type="text"
                    required
                    placeholder="Search and add product"
                    autocomplete="off"
                    @focus="openProductDropdown(idx, $event)"
                    @input="onProductSearchInput(item, idx)"
                    @blur="closeProductDropdownSoon"
                  />
                  <div v-if="activeProductDropdown === idx" class="select2-combobox__menu">
                    <button
                      v-for="product in filteredProducts(item)"
                      :key="product.id"
                      type="button"
                      class="select2-combobox__option"
                      @mousedown.prevent="selectProduct(item, product)"
                    >
                      <span>
                        <strong>{{ product.brand || product.name }}</strong>
                        <small>{{ product.name }}</small>
                      </span>
                      <em>+ Add</em>
                    </button>
                    <p v-if="!filteredProducts(item).length" class="select2-combobox__empty">No products found.</p>
                  </div>
                </div>
                <select
                  v-model.number="item.denomination"
                  class="avq-input"
                  :class="{ 'is-invalid': itemError(idx, 'denomination') }"
                  required
                  :disabled="!item.product_id || !productDenominations(item).length"
                  @change="mergePiDuplicateItems(idx)"
                >
                  <option value="">Denomination</option>
                  <option v-for="denomination in productDenominations(item)" :key="denomination" :value="denomination">
                    {{ selectedProduct(item)?.currency_code || 'INR' }} {{ fmt(denomination) }}
                  </option>
                </select>
                <input
                  v-model.number="item.quantity"
                  class="avq-input"
                  :class="{ 'is-invalid': itemError(idx, 'quantity') }"
                  type="number"
                  min="1"
                  required
                  placeholder="Qty"
                  @change="mergePiDuplicateItems(idx)"
                />
                <input v-model.number="item.discount_percentage" class="avq-input" type="number" step=".01" placeholder="Adj %" disabled />
                <input v-model.number="item.gst_rate" class="avq-input" type="number" step=".01" placeholder="GST %" disabled />
                <button type="button" class="avq-btn-sm" @click="piForm.items.splice(idx, 1)">×</button>
              </div>
            </div>
          </div>
          <button type="button" class="avq-btn-ghost" @click="addPiItem">+ Add item</button>
          <div class="modal-footer">
            <button type="button" class="avq-btn-ghost" @click="showPiModal = false">Cancel</button>
            <button type="submit" class="avq-btn-primary">Save PI</button>
          </div>
        </form>
      </div>

      <div v-if="showPaymentModal" class="avq-modal-overlay" @click.self="showPaymentModal = false">
        <form class="avq-modal payment-modal" @submit.prevent="capturePayment">
          <div class="payment-modal__head">
            <div>
              <h3>Capture Payment</h3>
              <p>Record client payment against a finalized Proforma Invoice.</p>
            </div>
          </div>
          <div class="payment-form-grid">
            <div v-if="paymentErrors.general.length" class="form-error-summary payment-form-error">
              <p v-for="message in paymentErrors.general" :key="message">{{ message }}</p>
            </div>
            <div class="payment-field payment-field--wide">
              <label>Proforma Invoice</label>
              <select v-model="paymentForm.proforma_invoice_id" class="avq-input" :class="{ 'is-invalid': paymentErrors.fields.proforma_invoice_id }" required>
                <option value="">Select PI with pending payment</option>
                <option v-for="pi in payablePis" :key="pi.id" :value="pi.id">
                  {{ pi.pi_number }} — {{ pi.customer?.company_name }} — Remaining ₹{{ fmt(remainingPiAmount(pi)) }}
                </option>
              </select>
              <small v-if="paymentErrors.fields.proforma_invoice_id" class="field-error">{{ paymentErrors.fields.proforma_invoice_id }}</small>
              <div v-if="selectedPaymentPi" class="payment-due-summary">
                <div><span>PI Total</span><strong>₹{{ fmt(selectedPaymentPi.total_amount) }}</strong></div>
                <div><span>Paid till now</span><strong>₹{{ fmt(selectedPaymentPi.paid_amount) }}</strong></div>
                <div><span>Remaining amount</span><strong>₹{{ fmt(remainingPiAmount(selectedPaymentPi)) }}</strong></div>
              </div>
            </div>

            <div class="payment-field">
              <label>Date</label>
              <input v-model="paymentForm.payment_date" type="date" class="avq-input" :class="{ 'is-invalid': paymentErrors.fields.payment_date }" :max="todayDate" required />
              <small v-if="paymentErrors.fields.payment_date" class="field-error">{{ paymentErrors.fields.payment_date }}</small>
            </div>

            <div class="payment-field">
              <label>Amount</label>
              <input v-model.number="paymentForm.amount" type="number" min="0.01" step="0.01" class="avq-input" :class="{ 'is-invalid': paymentErrors.fields.amount }" required placeholder="0.00" />
              <small v-if="paymentErrors.fields.amount" class="field-error">{{ paymentErrors.fields.amount }}</small>
            </div>

            <div class="payment-field">
              <label>Mode</label>
              <input v-model="paymentForm.mode" class="avq-input" :class="{ 'is-invalid': paymentErrors.fields.mode }" placeholder="NEFT / UPI / Bank Transfer" required />
              <small v-if="paymentErrors.fields.mode" class="field-error">{{ paymentErrors.fields.mode }}</small>
            </div>

            <div class="payment-field">
              <label>Reference / UTR</label>
              <input v-model="paymentForm.reference_no" class="avq-input" placeholder="Transaction reference" />
            </div>

            <div class="payment-field payment-field--wide">
              <label>Payment details</label>
              <textarea v-model="paymentForm.details" class="avq-input" :class="{ 'is-invalid': paymentErrors.fields.details }" required placeholder="Add payment note, bank details or confirmation remarks"></textarea>
              <small v-if="paymentErrors.fields.details" class="field-error">{{ paymentErrors.fields.details }}</small>
            </div>

            <div class="payment-field payment-field--wide">
              <label>Screenshot / proof <span>(optional)</span></label>
              <input type="file" class="avq-input payment-file-input" @change="paymentAttachment = $event.target.files?.[0] || null" />
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="avq-btn-ghost" @click="showPaymentModal = false">Cancel</button>
            <button type="submit" class="avq-btn-primary">Capture payment</button>
          </div>
        </form>
      </div>

      <div v-if="emailModal.open" class="avq-modal-overlay" @click.self="closeEmailModal">
        <form class="avq-modal billing-email-modal" @submit.prevent="sendInternalEmail">
          <h3>Email internal document</h3>
          <div class="billing-email-layout">
            <div class="billing-email-form">
              <label>To (avirqo.com / avirqo.in only)</label>
              <input v-model="emailModal.to_email" class="avq-input" :class="{ 'is-invalid': emailErrors.fields.to_email }" type="email" required placeholder="name@avirqo.com" />
              <small v-if="emailErrors.fields.to_email" class="field-error">{{ emailErrors.fields.to_email }}</small>
              <label>Message</label>
              <textarea v-model="emailModal.message" class="avq-input"></textarea>
              <div class="modal-footer">
                <button type="button" class="avq-btn-ghost" @click="closeEmailModal">Cancel</button>
                <button type="submit" class="avq-btn-primary">Send</button>
              </div>
            </div>
            <aside class="billing-preview-pane">
              <div class="billing-preview-head">
                <strong>Document preview</strong>
                <button type="button" class="link-button" @click="openPdfPreview(emailModal.type, emailModal.id, 'Document')">Open larger</button>
              </div>
              <p v-if="emailModal.previewLoading" class="preview-state">Loading preview…</p>
              <p v-else-if="emailModal.previewError" class="preview-state error">{{ emailModal.previewError }}</p>
              <iframe
                v-else-if="emailModal.previewUrl"
                class="billing-preview-frame"
                :src="emailModal.previewUrl"
                title="Document preview"
              />
            </aside>
          </div>
        </form>
      </div>

      <div v-if="pdfPreview.open" class="avq-modal-overlay" @click.self="closePdfPreview">
        <div class="avq-modal avq-modal-lg pdf-preview-modal">
          <div class="pdf-preview-title">
            <h3>{{ pdfPreview.title }}</h3>
            <button type="button" class="avq-btn-sm" @click="closePdfPreview">× Close</button>
          </div>
          <p v-if="pdfPreview.loading" class="preview-state">Loading PDF preview…</p>
          <p v-else-if="pdfPreview.error" class="preview-state error">{{ pdfPreview.error }}</p>
          <iframe v-else-if="pdfPreview.url" class="pdf-preview-frame" :src="pdfPreview.url" title="PDF preview" />
        </div>
      </div>

      <div v-if="invalidModal.open" class="avq-modal-overlay" @click.self="invalidModal.open = false">
        <form class="avq-modal" @submit.prevent="confirmInvalidatePayment">
          <h3>Mark payment invalid</h3>
          <p class="muted">This will reverse the PI-linked customer balance if it has not been used for voucher delivery.</p>
          <p v-if="invalidErrors.general.length" class="form-error">{{ invalidErrors.general[0] }}</p>
          <label>Reason</label>
          <textarea v-model="invalidModal.reason" class="avq-input" :class="{ 'is-invalid': invalidErrors.fields.reason }" required placeholder="Enter reason"></textarea>
          <small v-if="invalidErrors.fields.reason" class="field-error">{{ invalidErrors.fields.reason }}</small>
          <div class="modal-footer">
            <button type="button" class="avq-btn-ghost" @click="invalidModal.open = false">Cancel</button>
            <button type="submit" class="avq-btn-primary">Mark invalid</button>
          </div>
        </form>
      </div>

      <div v-if="creditApplyModal.open" class="avq-modal-overlay" @click.self="closeCreditApplyModal">
        <form class="avq-modal avq-modal-sm" @submit.prevent="applyCreditNoteToPiBalance">
          <h3>Apply Credit Note to PI</h3>
          <p class="muted">
            Select the pending-payment PI for the same customer. Balance will be added against the selected PI only.
          </p>
          <div v-if="creditApplyModal.note" class="finalize-summary">
            <div><span>Credit Note</span><strong>{{ creditApplyModal.note.note_number || creditApplyModal.note.draft_number }}</strong></div>
            <div><span>Customer</span><strong>{{ creditApplyModal.note.customer?.company_name || '—' }}</strong></div>
            <div><span>Available</span><strong>₹{{ fmt(creditApplyModal.note.remaining_amount) }}</strong></div>
          </div>

          <label>Pending PI</label>
          <select
            v-model="creditApplyModal.proforma_invoice_id"
            class="avq-input"
            :class="{ 'is-invalid': creditApplyModal.error && !creditApplyModal.proforma_invoice_id }"
            required
            @change="syncCreditApplyAmount"
          >
            <option value="">Select pending PI</option>
            <option v-for="pi in creditApplyModal.proformas" :key="pi.id" :value="pi.id">
              {{ pi.pi_number }} — Pending ₹{{ fmt(pi.applicable_amount) }}
            </option>
          </select>

          <div v-if="selectedCreditApplyPi" class="payment-due-summary single">
            <div><span>PI Total</span><strong>₹{{ fmt(selectedCreditApplyPi.total_amount) }}</strong></div>
            <div><span>Paid</span><strong>₹{{ fmt(selectedCreditApplyPi.paid_amount) }}</strong></div>
            <div><span>Can apply</span><strong>₹{{ fmt(selectedCreditApplyPi.applicable_amount) }}</strong></div>
          </div>

          <label>Amount to apply</label>
          <input
            v-model.number="creditApplyModal.amount"
            class="avq-input"
            type="number"
            min="0.01"
            step="0.01"
            :max="selectedCreditApplyPi?.applicable_amount || creditApplyModal.note?.remaining_amount || 0"
            required
            @focus="$event.target.select()"
          />

          <p v-if="creditApplyModal.loading" class="muted">Loading pending PIs…</p>
          <p v-if="creditApplyModal.error" class="field-error">{{ creditApplyModal.error }}</p>
          <p v-if="!creditApplyModal.loading && !creditApplyModal.proformas.length" class="field-error">
            No pending-payment PI found for this customer.
          </p>

          <div class="modal-footer">
            <button type="button" class="avq-btn-ghost" @click="closeCreditApplyModal">Cancel</button>
            <button type="submit" class="avq-btn-primary" :disabled="creditApplyModal.loading || !creditApplyModal.proformas.length">Apply</button>
          </div>
        </form>
      </div>

      <div v-if="finalizePiModal.open" class="avq-modal-overlay" @click.self="finalizePiModal.open = false">
        <form class="avq-modal avq-modal-sm" @submit.prevent="confirmFinalizePi">
          <h3>Finalize Proforma Invoice</h3>
          <p class="muted">
            Once finalized, this PI cannot be edited later. Please review customer, items, quantity,
            adjustment and total amount before finalizing.
          </p>
          <div v-if="finalizePiModal.pi" class="finalize-summary">
            <div><span>PI</span><strong>{{ finalizePiModal.pi.pi_number || finalizePiModal.pi.draft_number }}</strong></div>
            <div><span>Customer</span><strong>{{ finalizePiModal.pi.customer?.company_name || '—' }}</strong></div>
            <div><span>Total</span><strong>₹{{ fmt(finalizePiModal.pi.total_amount) }}</strong></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="avq-btn-ghost" @click="finalizePiModal.open = false">Cancel</button>
            <button type="submit" class="avq-btn-primary">Finalize</button>
          </div>
        </form>
      </div>

      <div v-if="cancelPiModal.open" class="avq-modal-overlay" @click.self="closeCancelPiModal">
        <form class="avq-modal avq-modal-sm" @submit.prevent="cancelPiModal.stage === 'otp' ? verifyCancelPiOtp() : requestCancelPiOtp()">
          <h3>Cancel Proforma Invoice</h3>
          <p class="muted">
            Cancelling this PI will disable it. If payment balance was added and no vouchers were delivered,
            the balance will be reversed after OTP approval.
          </p>
          <div v-if="cancelPiModal.pi" class="finalize-summary">
            <div><span>PI</span><strong>{{ cancelPiModal.pi.pi_number || cancelPiModal.pi.draft_number }}</strong></div>
            <div><span>Customer</span><strong>{{ cancelPiModal.pi.customer?.company_name || '—' }}</strong></div>
            <div><span>Total</span><strong>₹{{ fmt(cancelPiModal.pi.total_amount) }}</strong></div>
          </div>

          <template v-if="cancelPiModal.stage === 'otp'">
            <div class="otp-info">
              OTP sent to {{ cancelPiModal.recipients.join(' and ') }}. OTP valid for 10 minutes.
              The PI PDF is attached in the approval mail.
            </div>
            <label>Enter 6-Digit OTP</label>
            <input
              v-model="cancelPiModal.otp"
              class="avq-input otp-input"
              maxlength="6"
              inputmode="numeric"
              placeholder="123456"
              required
            />
            <button type="button" class="avq-btn-ghost resend-btn" @click="resendCancelPiOtp">Resend OTP</button>
          </template>

          <p v-if="cancelPiModal.error" class="field-error">{{ cancelPiModal.error }}</p>

          <div class="modal-footer">
            <button type="button" class="avq-btn-ghost" @click="closeCancelPiModal">Close</button>
            <button type="submit" class="avq-btn-primary" :disabled="cancelPiModal.loading">
              {{ cancelPiModal.stage === 'otp' ? 'Verify OTP & Cancel PI' : 'Send OTP' }}
            </button>
          </div>
        </form>
      </div>

      <AppToast :open="!!toast" :message="toast" @close="toast = ''" />
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AppLayout from '../../shared/components/AppLayout.vue';
import AppToast from '../../shared/components/AppToast.vue';
import billingApi from '../api/billingApi';

const tabs = [
  { key: 'proformas', label: 'Proforma Invoices', path: '/billing/proforma-invoices' },
  { key: 'tax', label: 'Tax Invoices', path: '/billing/tax-invoices' },
  { key: 'payments', label: 'Payments', path: '/billing/payments' },
  { key: 'notes', label: 'Credit/Debit Notes', path: '/billing/credit-debit-notes' },
  { key: 'reports', label: 'Reports', path: '/billing/reports' },
  { key: 'approvers', label: 'OTP Approvers', path: '/billing/otp-approvers' },
];

const route = useRoute();
const router = useRouter();
const tabToPath = Object.fromEntries(tabs.map(item => [item.key, item.path]));
const tab = computed(() => route.meta.billingTab || 'proformas');
const filters = ref({ search: '', taxSearch: '', paymentsSearch: '', notesSearch: '' });
const proformas = ref([]);
const taxInvoices = ref([]);
const payments = ref([]);
const notes = ref([]);
const customers = ref([]);
const products = ref([]);
const approvers = ref([]);
const availableCreditNotes = ref([]);
const campaignDiscounts = ref({});
const campaignBlacklistedProductIds = ref([]);
const selectedCampaign = ref(null);
const toast = ref('');
const showPiModal = ref(false);
const showPaymentModal = ref(false);
const paymentAttachment = ref(null);
const todayDate = localDateString();
const piIssueDateMin = localDateString(daysAgo(5));
const piForm = ref(blankPi());
const paymentForm = ref(blankPayment());
const emailModal = ref({ open: false, type: '', id: null, to_email: '', message: '', previewUrl: '', previewLoading: false, previewError: '' });
const invalidModal = ref({ open: false, payment: null, reason: '' });
const creditApplyModal = ref({ open: false, note: null, proformas: [], proforma_invoice_id: '', amount: '', loading: false, error: '' });
const finalizePiModal = ref({ open: false, pi: null });
const cancelPiModal = ref({ open: false, pi: null, stage: 'confirm', requestId: '', otp: '', recipients: [], loading: false, error: '' });
const pdfPreview = ref({ open: false, type: '', id: null, title: '', url: '', loading: false, error: '' });
const activeProductDropdown = ref(null);
const showCustomerDropdown = ref(false);
const piErrors = ref(blankValidationErrors());
const paymentErrors = ref(blankValidationErrors());
const emailErrors = ref(blankValidationErrors());
const invalidErrors = ref(blankValidationErrors());
const approverErrors = ref({});

const payablePis = computed(() => proformas.value.filter(pi => (
  ['finalized', 'paid', 'partially_delivered'].includes(pi.status)
  && remainingPiAmount(pi) > 0
)));
const selectedPaymentPi = computed(() => payablePis.value.find(pi => Number(pi.id) === Number(paymentForm.value.proforma_invoice_id)) || null);
const selectedCreditApplyPi = computed(() => creditApplyModal.value.proformas.find(pi => Number(pi.id) === Number(creditApplyModal.value.proforma_invoice_id)) || null);
const filteredCustomers = computed(() => {
  const term = String(piForm.value.customerSearch || '').trim().toLowerCase();
  const list = term
    ? customers.value.filter(customer => customerLabel(customer).toLowerCase().includes(term))
    : customers.value;
  return list.slice(0, 12);
});

onMounted(async () => {
  await Promise.all([loadCustomers(), loadProducts(), loadAll()]);
});

onBeforeUnmount(() => {
  revokePdfUrl(emailModal.value.previewUrl);
  revokePdfUrl(pdfPreview.value.url);
});

watch(() => route.meta.billingTab, async (billingTab) => {
  if (!billingTab) {
    await router.replace(tabToPath.proformas);
    return;
  }
  await loadAll();
});

async function loadAll() {
  if (tab.value === 'proformas' || tab.value === 'payments') await loadProformas();
  if (tab.value === 'tax') await loadTaxInvoices();
  if (tab.value === 'payments') await loadPayments();
  if (tab.value === 'notes') await loadNotes();
  if (tab.value === 'approvers') await loadApprovers();
}

async function loadCustomers() {
  const { data } = await billingApi.customers();
  customers.value = data.data || data;
}

async function loadProducts() {
  const { data } = await billingApi.products();
  products.value = data.data || [];
}

async function loadProformas() {
  const { data } = await billingApi.proformas(filters.value);
  proformas.value = data.data || [];
}
async function loadTaxInvoices() {
  const { data } = await billingApi.taxInvoices({ search: filters.value.taxSearch });
  taxInvoices.value = data.data || [];
}
async function loadPayments() {
  const { data } = await billingApi.payments({ search: filters.value.paymentsSearch });
  payments.value = data.data || [];
}
async function loadNotes() {
  const { data } = await billingApi.notes({ search: filters.value.notesSearch });
  notes.value = data.data || [];
}
async function loadApprovers() {
  const { data } = await billingApi.approvers();
  approvers.value = (data.data || []).map(g => ({ ...g, emailText: (g.emails || []).join(', ') }));
}

function blankPi() {
  return {
    id: null,
    customer_id: '',
    customerSearch: '',
    issue_date: new Date().toISOString().slice(0, 10),
    valid_until: '',
    discount_type: 'campaign',
    invoice_discount_percentage: 0,
    notes: '',
    credit_note_applications: [],
    items: [blankItem()],
  };
}
function blankItem() {
  return { product_id: '', productSearch: '', denomination: '', quantity: 1, discount_percentage: 0, gst_rate: 0 };
}
function blankPayment() {
  return { proforma_invoice_id: '', payment_date: todayDate, amount: '', mode: 'NEFT', reference_no: '', details: '' };
}
function blankValidationErrors() {
  return { general: [], fields: {}, items: [] };
}
function remainingPiAmount(pi) {
  return Math.max(0, Number(pi?.total_amount || 0) - Number(pi?.paid_amount || 0));
}
function localDateString(date = new Date()) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}
function daysAgo(days) {
  const date = new Date();
  date.setDate(date.getDate() - days);
  return date;
}
function openPiModal(pi = null) {
  piErrors.value = blankValidationErrors();
  piForm.value = pi ? {
    id: pi.id,
    customer_id: pi.customer_id,
    customerSearch: customerLabel(customers.value.find(c => Number(c.id) === Number(pi.customer_id))) || pi.customer?.company_name || '',
    issue_date: pi.issue_date || new Date().toISOString().slice(0, 10),
    valid_until: pi.valid_until || '',
    discount_type: pi.discount_type || 'campaign',
    invoice_discount_percentage: Number(pi.invoice_discount_percentage || 0),
    notes: pi.notes || '',
    credit_note_applications: (pi.credit_note_applications || [])
      .filter(application => application.status === 'reserved')
      .map(application => ({
        credit_note_id: application.billing_credit_debit_note_id,
        amount: Number(application.amount || 0),
      })),
    items: (pi.items || []).map(i => ({
      product_id: i.product_id,
      productSearch: productLabel(products.value.find(p => Number(p.id) === Number(i.product_id))) || i.product?.name || '',
      denomination: Number(i.denomination),
      quantity: i.quantity,
      discount_percentage: Number(i.discount_percentage || 0),
      gst_rate: 0,
      hsn_sac: i.hsn_sac,
    })),
  } : blankPi();
  showPiModal.value = true;
  availableCreditNotes.value = [];
  if (piForm.value.customer_id) {
    loadCampaignDiscounts(piForm.value.customer_id, !pi);
    loadCustomerCreditNotes();
  }
}
function addPiItem() { piForm.value.items.push(blankItem()); }
function itemError(index, field) {
  return piErrors.value.items?.[index]?.[field] || '';
}
function customerLabel(customer) {
  return customer ? `${customer.company_name}${customer.location ? ` — ${customer.location}` : ''}` : '';
}
function openCustomerDropdown(event) {
  showCustomerDropdown.value = true;
  event?.target?.select?.();
}
function closeCustomerDropdownSoon() {
  window.setTimeout(() => {
    showCustomerDropdown.value = false;
  }, 120);
}
function onCustomerSearchInput() {
  piErrors.value.fields.customer_id = '';
  showCustomerDropdown.value = true;
  const selected = customers.value.find(c => Number(c.id) === Number(piForm.value.customer_id));
  if (selected && piForm.value.customerSearch !== customerLabel(selected)) {
    piForm.value.customer_id = '';
    selectedCampaign.value = null;
    campaignDiscounts.value = {};
    campaignBlacklistedProductIds.value = [];
    piForm.value.credit_note_applications = [];
    availableCreditNotes.value = [];
    applyDiscountMode();
  }
}
async function selectCustomer(customer) {
  piForm.value.customer_id = customer.id;
  piForm.value.customerSearch = customerLabel(customer);
  showCustomerDropdown.value = false;
  piForm.value.credit_note_applications = [];
  await loadCampaignDiscounts(customer.id, true);
  await loadCustomerCreditNotes();
}
async function loadCustomerCreditNotes() {
  if (!piForm.value.customer_id) {
    availableCreditNotes.value = [];
    return;
  }
  const { data } = await billingApi.customerCreditNotes(piForm.value.customer_id, {
    proforma_invoice_id: piForm.value.id || undefined,
  });
  availableCreditNotes.value = data.data || [];
}
function isCreditNoteSelected(noteId) {
  return piForm.value.credit_note_applications.some(application => Number(application.credit_note_id) === Number(noteId));
}
function selectedCreditNote(noteId) {
  return piForm.value.credit_note_applications.find(application => Number(application.credit_note_id) === Number(noteId)) || {};
}
function toggleCreditNote(note, checked) {
  piForm.value.credit_note_applications = piForm.value.credit_note_applications.filter(application => Number(application.credit_note_id) !== Number(note.id));
  if (checked) {
    const amount = Math.min(Number(note.available_amount || 0), remainingDraftPiAmount());
    piForm.value.credit_note_applications.push({ credit_note_id: note.id, amount: amount > 0 ? amount : Number(note.available_amount || 0) });
  }
}
function draftPiTotal() {
  const subtotal = piForm.value.items.reduce((sum, item) => {
    const quantity = Number(item.quantity || 0);
    const denomination = Number(item.denomination || 0);
    return sum + (quantity * denomination);
  }, 0);
  if (piForm.value.discount_type === 'invoice') {
    return Math.round(subtotal - (subtotal * Number(piForm.value.invoice_discount_percentage || 0) / 100));
  }
  const total = piForm.value.items.reduce((sum, item) => {
    const gross = Number(item.quantity || 0) * Number(item.denomination || 0);
    const adjustment = gross * Number(item.discount_percentage || 0) / 100;
    return sum + gross - adjustment;
  }, 0);
  return Math.round(total);
}
function selectedCreditNoteTotal() {
  return piForm.value.credit_note_applications.reduce((sum, application) => sum + Number(application.amount || 0), 0);
}
function remainingDraftPiAmount() {
  return Math.max(0, draftPiTotal() - selectedCreditNoteTotal());
}
function productLabel(product) {
  return product ? `${product.brand || product.name} — ${product.name}` : '';
}
function openProductDropdown(idx, event) {
  activeProductDropdown.value = idx;
  event?.target?.select?.();
}
function closeProductDropdownSoon() {
  window.setTimeout(() => {
    activeProductDropdown.value = null;
  }, 120);
}
function onProductSearchInput(item, idx) {
  if (piErrors.value.items?.[idx]) piErrors.value.items[idx].product_id = '';
  activeProductDropdown.value = idx;
  const selected = products.value.find(p => Number(p.id) === Number(item.product_id));
  if (selected && item.productSearch !== productLabel(selected)) {
    item.product_id = '';
  }
}
function filteredProducts(item) {
  if (piForm.value.customer_id && !selectedCampaign.value) return [];

  const term = String(item.productSearch || '').trim().toLowerCase();
  const list = term
    ? products.value.filter(product => productLabel(product).toLowerCase().includes(term))
    : products.value;
  return list.filter(product => !isCampaignBlacklisted(product.id)).slice(0, 12);
}
function selectProduct(item, product) {
  item.product_id = product.id;
  item.productSearch = productLabel(product);
  item.denomination = '';
  syncProduct(item);
  applyItemDiscount(item);
  activeProductDropdown.value = null;
  nextTick(() => mergePiDuplicateItems());
}
function syncProduct(item) {
  const product = selectedProduct(item);
  if (!product) return;
  const denoms = productDenominations(item);
  item.denomination = item.denomination || Number(denoms[0] || 0);
  item.gst_rate = 0;
}
function selectedProduct(item) {
  return products.value.find(p => Number(p.id) === Number(item.product_id)) || null;
}
function productDenominations(item) {
  const product = selectedProduct(item);
  return (product?.denominations || []).map(Number).filter(value => value > 0);
}
async function loadCampaignDiscounts(customerId, applyToItems = true) {
  if (!customerId) {
    selectedCampaign.value = null;
    campaignDiscounts.value = {};
    campaignBlacklistedProductIds.value = [];
    applyDiscountMode();
    return;
  }

  const { data } = await billingApi.customerCampaignDiscounts(customerId);
  selectedCampaign.value = data.campaign || null;
  campaignDiscounts.value = data.discounts || {};
  campaignBlacklistedProductIds.value = (data.blacklisted_product_ids || []).map(Number);
  clearCampaignBlacklistedItems();
  if (applyToItems) applyDiscountMode();
}
function isCampaignBlacklisted(productId) {
  return campaignBlacklistedProductIds.value.includes(Number(productId));
}
function clearCampaignBlacklistedItems() {
  piForm.value.items.forEach(item => {
    if (item.product_id && isCampaignBlacklisted(item.product_id)) {
      item.product_id = '';
      item.productSearch = '';
      item.denomination = '';
      item.discount_percentage = 0;
    }
  });
}
function applyDiscountMode() {
  piForm.value.items.forEach(applyItemDiscount);
}
function applyItemDiscount(item) {
  item.gst_rate = 0;
  if (item.product_id && isCampaignBlacklisted(item.product_id)) {
    item.discount_percentage = 0;
    return;
  }
  if (piForm.value.discount_type === 'invoice') {
    item.discount_percentage = 0;
    return;
  }
  item.discount_percentage = Number(campaignDiscounts.value?.[item.product_id] || 0);
}
function mergePiDuplicateItems(preferredIndex = null) {
  const merged = [];
  const indexByKey = new Map();

  piForm.value.items.forEach((item, index) => {
    if (!item.product_id || !item.denomination) {
      merged.push(item);
      return;
    }

    const key = `${Number(item.product_id)}|${Number(item.denomination).toFixed(2)}`;
    const existingIndex = indexByKey.get(key);
    if (existingIndex === undefined) {
      indexByKey.set(key, merged.length);
      merged.push(item);
      return;
    }

    const existing = merged[existingIndex];
    existing.quantity = Number(existing.quantity || 0) + Number(item.quantity || 0);
    if (preferredIndex === index) {
      existing.productSearch = item.productSearch;
      existing.discount_percentage = item.discount_percentage;
      existing.gst_rate = item.gst_rate;
    }
  });

  piForm.value.items = merged.length ? merged : [blankItem()];
}
async function savePi() {
  if (!validatePiForm()) return;

  mergePiDuplicateItems();

  const payload = {
    ...piForm.value,
    invoice_discount_percentage: piForm.value.discount_type === 'invoice' ? Number(piForm.value.invoice_discount_percentage || 0) : 0,
    items: piForm.value.items
      .filter(i => i.product_id && i.denomination && i.quantity)
      .map(({ productSearch, ...item }) => ({ ...item, gst_rate: 0 })),
  };
  delete payload.customerSearch;
  if (payload.id) delete payload.issue_date;
  if (payload.id) await billingApi.updateProforma(payload.id, payload);
  else await billingApi.createProforma(payload);
  showPiModal.value = false;
  toast.value = 'Proforma Invoice saved.';
  await loadProformas();
}
function validatePiForm() {
  const errors = blankValidationErrors();

  if (!piForm.value.customer_id) {
    errors.fields.customer_id = 'Customer is required.';
    errors.general.push('Customer is required.');
  }

  if (!piForm.value.issue_date) {
    errors.fields.issue_date = 'Issue date is required.';
    errors.general.push('Issue date is required.');
  } else if (piForm.value.issue_date < piIssueDateMin || piForm.value.issue_date > todayDate) {
    errors.fields.issue_date = 'Issue date can only be today or one of the previous 5 dates.';
    errors.general.push('Issue date can only be today or one of the previous 5 dates.');
  }

  if (!piForm.value.items.length) {
    errors.general.push('At least one item is required.');
  }

  const creditNoteTotal = selectedCreditNoteTotal();
  if (creditNoteTotal > draftPiTotal()) {
    errors.general.push('Credit note adjustment cannot be more than the PI total.');
  }
  piForm.value.credit_note_applications.forEach((application) => {
    const note = availableCreditNotes.value.find(item => Number(item.id) === Number(application.credit_note_id));
    if (!note) {
      errors.general.push('Selected credit note is no longer available.');
      return;
    }
    if (!application.amount || Number(application.amount) <= 0) {
      errors.general.push(`Credit Note ${note.note_number || note.draft_number}: amount must be greater than 0.`);
    }
    if (Number(application.amount || 0) > Number(note.available_amount || 0)) {
      errors.general.push(`Credit Note ${note.note_number || note.draft_number}: amount cannot exceed available amount.`);
    }
  });

  piForm.value.items.forEach((item, index) => {
    const rowErrors = {};
    if (!item.product_id) rowErrors.product_id = 'Product is required.';
    if (item.product_id && isCampaignBlacklisted(item.product_id)) rowErrors.product_id = 'This product is blacklisted for the selected customer campaign.';
    if (!item.denomination) rowErrors.denomination = 'Denomination is required.';
    if (!item.quantity || Number(item.quantity) < 1) rowErrors.quantity = 'Quantity must be at least 1.';
    if (Object.keys(rowErrors).length) {
      errors.items[index] = rowErrors;
      errors.general.push(`Item ${index + 1}: ${Object.values(rowErrors).join(' ')}`);
    }
  });

  piErrors.value = errors;
  return !errors.general.length;
}
function openFinalizePiModal(pi) {
  finalizePiModal.value = { open: true, pi };
}
async function confirmFinalizePi() {
  if (!finalizePiModal.value.pi) return;
  await billingApi.finalizeProforma(finalizePiModal.value.pi.id);
  finalizePiModal.value = { open: false, pi: null };
  toast.value = 'Proforma Invoice finalized.';
  await loadProformas();
}
function openCancelPiModal(pi) {
  cancelPiModal.value = { open: true, pi, stage: 'confirm', requestId: '', otp: '', recipients: [], loading: false, error: '' };
}
function closeCancelPiModal() {
  cancelPiModal.value = { open: false, pi: null, stage: 'confirm', requestId: '', otp: '', recipients: [], loading: false, error: '' };
}
async function requestCancelPiOtp() {
  if (!cancelPiModal.value.pi) return;
  cancelPiModal.value.loading = true;
  cancelPiModal.value.error = '';
  try {
    const { data } = await billingApi.requestCancelProformaOtp(cancelPiModal.value.pi.id);
    cancelPiModal.value.stage = 'otp';
    cancelPiModal.value.requestId = data.request_id;
    cancelPiModal.value.recipients = data.recipients || [];
    toast.value = data.message || 'OTP sent for PI cancellation.';
  } catch (error) {
    cancelPiModal.value.error = error.response?.data?.message || 'Unable to send OTP.';
  } finally {
    cancelPiModal.value.loading = false;
  }
}
async function resendCancelPiOtp() {
  if (!cancelPiModal.value.pi || !cancelPiModal.value.requestId) return;
  cancelPiModal.value.loading = true;
  cancelPiModal.value.error = '';
  try {
    const { data } = await billingApi.resendCancelProformaOtp(cancelPiModal.value.pi.id, cancelPiModal.value.requestId);
    cancelPiModal.value.recipients = data.recipients || cancelPiModal.value.recipients;
    toast.value = data.message || 'OTP resent successfully.';
  } catch (error) {
    cancelPiModal.value.error = error.response?.data?.message || 'Unable to resend OTP.';
  } finally {
    cancelPiModal.value.loading = false;
  }
}
async function verifyCancelPiOtp() {
  if (!cancelPiModal.value.pi || !cancelPiModal.value.requestId) return;
  cancelPiModal.value.loading = true;
  cancelPiModal.value.error = '';
  try {
    await billingApi.verifyCancelProformaOtp(cancelPiModal.value.pi.id, {
      request_id: cancelPiModal.value.requestId,
      otp: cancelPiModal.value.otp,
    });
    closeCancelPiModal();
    toast.value = 'Proforma Invoice cancelled.';
    await loadProformas();
  } catch (error) {
    cancelPiModal.value.error = error.response?.data?.message || 'Unable to cancel PI.';
  } finally {
    cancelPiModal.value.loading = false;
  }
}
async function cancelPi(pi) {
  await billingApi.cancelProforma(pi.id);
  toast.value = 'Proforma Invoice cancelled.';
  await loadProformas();
}
function openPaymentModal() {
  paymentForm.value = blankPayment();
  paymentErrors.value = blankValidationErrors();
  paymentAttachment.value = null;
  showPaymentModal.value = true;
}
async function capturePayment() {
  if (!validatePaymentForm()) return;

  const fd = new FormData();
  Object.entries(paymentForm.value).forEach(([k, v]) => fd.append(k, v ?? ''));
  if (paymentAttachment.value) fd.append('attachment', paymentAttachment.value);
  try {
    await billingApi.capturePayment(fd);
    showPaymentModal.value = false;
    toast.value = 'Payment captured.';
    await Promise.all([loadProformas(), loadPayments()]);
  } catch (error) {
    const response = error?.response?.data;
    const fieldErrors = response?.errors || {};
    const messages = Object.values(fieldErrors).flat().filter(Boolean);
    paymentErrors.value = {
      general: messages.length ? messages : [response?.message || 'Payment could not be captured. Please check the details and try again.'],
      fields: Object.fromEntries(Object.entries(fieldErrors).map(([field, value]) => [field, Array.isArray(value) ? value[0] : value])),
      items: [],
    };
  }
}
function validatePaymentForm() {
  const errors = blankValidationErrors();
  if (!paymentForm.value.proforma_invoice_id) {
    errors.fields.proforma_invoice_id = 'Proforma Invoice is required.';
    errors.general.push('Proforma Invoice is required.');
  } else if (!selectedPaymentPi.value) {
    errors.fields.proforma_invoice_id = 'This PI has no remaining payment due.';
    errors.general.push('This PI has no remaining payment due.');
  }
  if (!paymentForm.value.payment_date) {
    errors.fields.payment_date = 'Payment date is required.';
    errors.general.push('Payment date is required.');
  } else if (paymentForm.value.payment_date > todayDate) {
    errors.fields.payment_date = 'Payment date cannot be in the future.';
    errors.general.push('Payment date cannot be in the future.');
  }
  if (!paymentForm.value.amount || Number(paymentForm.value.amount) <= 0) {
    errors.fields.amount = 'Amount must be greater than 0.';
    errors.general.push('Amount must be greater than 0.');
  }
  if (!String(paymentForm.value.mode || '').trim()) {
    errors.fields.mode = 'Payment mode is required.';
    errors.general.push('Payment mode is required.');
  }
  if (!String(paymentForm.value.details || '').trim()) {
    errors.fields.details = 'Payment details are required.';
    errors.general.push('Payment details are required.');
  }
  paymentErrors.value = errors;
  return !errors.general.length;
}
async function invalidatePayment(payment) {
  invalidErrors.value = blankValidationErrors();
  invalidModal.value = { open: true, payment, reason: '' };
}
async function confirmInvalidatePayment() {
  if (!validateInvalidPaymentForm()) return;

  try {
    await billingApi.invalidatePayment(invalidModal.value.payment.id, invalidModal.value.reason);
    invalidModal.value.open = false;
    toast.value = 'Payment marked invalid.';
    await Promise.all([loadProformas(), loadPayments()]);
  } catch (error) {
    const message = error.response?.data?.message
      || Object.values(error.response?.data?.errors || {})?.flat()?.[0]
      || 'Unable to mark payment invalid.';
    invalidErrors.value = { ...blankValidationErrors(), general: [message] };
  }
}
function validateInvalidPaymentForm() {
  const errors = blankValidationErrors();
  if (!String(invalidModal.value.reason || '').trim()) {
    errors.fields.reason = 'Reason is required.';
    errors.general.push('Reason is required.');
  }
  invalidErrors.value = errors;
  return !errors.general.length;
}
function canApplyCreditNote(note) {
  return note.type === 'credit'
    && note.status === 'active'
    && Number(note.remaining_amount || 0) > 0;
}
async function openCreditApplyModal(note) {
  creditApplyModal.value = { open: true, note, proformas: [], proforma_invoice_id: '', amount: '', loading: true, error: '' };
  try {
    const { data } = await billingApi.pendingProformasForCreditNote(note.id);
    creditApplyModal.value.proformas = data.data || [];
    if (creditApplyModal.value.proformas.length === 1) {
      creditApplyModal.value.proforma_invoice_id = creditApplyModal.value.proformas[0].id;
      syncCreditApplyAmount();
    }
  } catch (error) {
    creditApplyModal.value.error = error.response?.data?.message || 'Unable to load pending PIs.';
  } finally {
    creditApplyModal.value.loading = false;
  }
}
function closeCreditApplyModal() {
  creditApplyModal.value = { open: false, note: null, proformas: [], proforma_invoice_id: '', amount: '', loading: false, error: '' };
}
function syncCreditApplyAmount() {
  const pi = selectedCreditApplyPi.value;
  if (!pi || !creditApplyModal.value.note) {
    creditApplyModal.value.amount = '';
    return;
  }
  creditApplyModal.value.amount = Math.min(Number(creditApplyModal.value.note.remaining_amount || 0), Number(pi.applicable_amount || 0));
}
async function applyCreditNoteToPiBalance() {
  const note = creditApplyModal.value.note;
  const pi = selectedCreditApplyPi.value;
  if (!note || !pi) {
    creditApplyModal.value.error = 'Please select a pending PI.';
    return;
  }
  if (!creditApplyModal.value.amount || Number(creditApplyModal.value.amount) <= 0) {
    creditApplyModal.value.error = 'Amount must be greater than 0.';
    return;
  }
  if (Number(creditApplyModal.value.amount) > Number(pi.applicable_amount || 0)) {
    creditApplyModal.value.error = 'Amount cannot be more than the pending amount for selected PI.';
    return;
  }
  try {
    await billingApi.applyCreditNoteToPiBalance(note.id, {
      proforma_invoice_id: creditApplyModal.value.proforma_invoice_id,
      amount: creditApplyModal.value.amount,
    });
    closeCreditApplyModal();
    toast.value = 'Credit note applied to PI balance.';
    await Promise.all([loadNotes(), loadProformas(), loadPayments()]);
  } catch (error) {
    creditApplyModal.value.error = error.response?.data?.message || 'Unable to apply credit note.';
  }
}
async function openEmailModal(type, doc) {
  emailErrors.value = blankValidationErrors();
  closeEmailPreviewUrl();
  emailModal.value = {
    open: true,
    type,
    id: doc.id,
    to_email: '',
    message: '',
    previewUrl: '',
    previewLoading: true,
    previewError: '',
  };
  await loadEmailPreview();
}
function closeEmailModal() {
  closeEmailPreviewUrl();
  emailModal.value = { open: false, type: '', id: null, to_email: '', message: '', previewUrl: '', previewLoading: false, previewError: '' };
}
function closeEmailPreviewUrl() {
  revokePdfUrl(emailModal.value.previewUrl);
}
async function loadEmailPreview() {
  try {
    const url = await createPdfBlobUrl(emailModal.value.type, emailModal.value.id);
    emailModal.value.previewUrl = url;
    emailModal.value.previewError = '';
  } catch (error) {
    emailModal.value.previewError = error.response?.data?.message || 'Could not load document preview.';
  } finally {
    emailModal.value.previewLoading = false;
  }
}
async function sendInternalEmail() {
  if (!validateEmailForm()) return;

  await billingApi.emailInternal(emailModal.value.type, emailModal.value.id, { to_email: emailModal.value.to_email, message: emailModal.value.message });
  closeEmailModal();
  toast.value = 'Document emailed internally.';
}
function validateEmailForm() {
  const errors = blankValidationErrors();
  const email = String(emailModal.value.to_email || '').trim();
  const allowed = /^[^\s@]+@(?:avirqo\.com|avirqo\.in)$/i.test(email);
  if (!email) {
    errors.fields.to_email = 'To email is required.';
    errors.general.push('To email is required.');
  } else if (!allowed) {
    errors.fields.to_email = 'Only avirqo.com or avirqo.in email IDs are allowed.';
    errors.general.push('Only avirqo.com or avirqo.in email IDs are allowed.');
  }
  emailErrors.value = errors;
  return !errors.general.length;
}
async function saveApprovers() {
  if (!validateApproversForm()) return;

  const groups = approvers.value.map(g => ({ ...g, emails: g.emailText.split(',').map(e => e.trim()).filter(Boolean) }));
  await billingApi.saveApprovers(groups);
  toast.value = 'OTP approvers saved.';
  await loadApprovers();
}
function validateApproversForm() {
  const errors = {};
  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  approvers.value.forEach((group) => {
    const emails = String(group.emailText || '').split(',').map(e => e.trim()).filter(Boolean);
    if (!emails.length) {
      errors[group.group_key] = 'At least one approver email is required.';
      return;
    }
    if (emails.some(email => !emailPattern.test(email))) {
      errors[group.group_key] = 'Enter valid comma-separated email IDs.';
    }
  });
  approverErrors.value = errors;
  return !Object.keys(errors).length;
}
async function openPdfPreview(type, id, title = 'Document') {
  closePdfPreview();
  pdfPreview.value = { open: true, type, id, title, url: '', loading: true, error: '' };
  try {
    pdfPreview.value.url = await createPdfBlobUrl(type, id);
  } catch (error) {
    pdfPreview.value.error = error.response?.data?.message || 'Could not load PDF preview.';
  } finally {
    pdfPreview.value.loading = false;
  }
}
function closePdfPreview() {
  revokePdfUrl(pdfPreview.value.url);
  pdfPreview.value = { open: false, type: '', id: null, title: '', url: '', loading: false, error: '' };
}
async function createPdfBlobUrl(type, id) {
  const { data } = await billingApi.documentBlob(type, id);
  return URL.createObjectURL(new Blob([data], { type: 'application/pdf' }));
}
function revokePdfUrl(url) {
  if (url) URL.revokeObjectURL(url);
}
function fmt(value) { return Number(value || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 }); }
function formatFriendlyDate(value) {
  if (!value) return '—';
  const datePart = String(value).slice(0, 10);
  const [year, month, day] = datePart.split('-').map(Number);
  if (!year || !month || !day) return value;
  return new Date(year, month - 1, day).toLocaleDateString('en-IN', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}
</script>

<style scoped>
.billing-page { padding: 28px; }
.billing-header { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; margin-bottom:20px; }
.billing-header h1 { font-family:var(--fd); font-size:32px; margin:0; }
.billing-header p, .muted { color:var(--ink-muted); margin:4px 0 0; }
.billing-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
.billing-tab {
  border:1px solid var(--border-2);
  background:#fff;
  color:var(--ink);
  border-radius:999px;
  padding:8px 14px;
  cursor:pointer;
  font-weight:600;
  text-decoration:none;
  display:inline-flex;
  align-items:center;
}
.billing-tab.active { background:var(--teal-deep); color:#fff; border-color:var(--teal-deep); }
.billing-card { background:#fff; border:1px solid var(--border-2); border-radius:16px; padding:18px; overflow:auto; }
.billing-toolbar { display:flex; justify-content:space-between; gap:12px; margin-bottom:12px; }
.billing-toolbar .avq-input { max-width:360px; }
.billing-table { width:100%; border-collapse:collapse; font-size:13px; min-width:900px; }
.billing-table th { text-align:left; color:var(--ink-muted); background:var(--surface-2); padding:10px; }
.billing-table td { border-top:1px solid var(--border-2); padding:10px; vertical-align:top; }
.num { text-align:right; font-weight:700; color:var(--teal-deep); }
.empty { text-align:center; color:var(--ink-muted); padding:36px !important; }
.badge { background:var(--teal-pale); color:var(--teal-deep); border-radius:999px; padding:4px 9px; font-weight:700; font-size:11px; text-transform:capitalize; }
.actions { display:flex; gap:6px; flex-wrap:wrap; }
.form-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
label { display:block; font-size:12px; color:var(--ink-muted); font-weight:700; margin:10px 0 4px; }
.payment-modal {
  max-width:920px;
}
.payment-modal__head {
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:16px;
  margin-bottom:18px;
  padding-bottom:14px;
  border-bottom:1px solid var(--border-2);
}
.payment-modal__head h3 {
  margin:0;
}
.payment-modal__head p {
  margin:6px 0 0;
  color:var(--ink-muted);
  font-size:14px;
}
.payment-form-grid {
  display:grid;
  grid-template-columns:repeat(2, minmax(0, 1fr));
  gap:16px 18px;
}
.payment-field {
  min-width:0;
}
.payment-field--wide {
  grid-column:1 / -1;
}
.payment-field label {
  margin-top:0;
}
.payment-field label span {
  color:var(--ink-muted);
  font-weight:600;
}
.payment-field .avq-input {
  width:100%;
  box-sizing:border-box;
}
.payment-due-summary {
  display:grid;
  grid-template-columns:repeat(3, minmax(0, 1fr));
  gap:10px;
  margin-top:12px;
}
.payment-due-summary div {
  background:var(--surface-2);
  border:1px solid var(--border-2);
  border-radius:12px;
  padding:10px 12px;
}
.payment-due-summary span {
  display:block;
  color:var(--ink-muted);
  font-size:12px;
  font-weight:700;
  margin-bottom:4px;
}
.payment-due-summary strong {
  color:var(--teal-deep);
  font-size:16px;
}
.payment-field textarea.avq-input {
  min-height:110px;
  resize:vertical;
}
.payment-file-input {
  padding:12px;
  height:auto;
}
.payment-file-input::file-selector-button {
  border:1px solid var(--border-2);
  background:#fff;
  border-radius:10px;
  padding:8px 12px;
  margin-right:12px;
  color:var(--ink);
  font:700 13px var(--fb, Inter, system-ui, sans-serif);
  cursor:pointer;
}
.payment-file-input::file-selector-button:hover {
  border-color:var(--teal-deep);
  color:var(--teal-deep);
}
.credit-note-panel {
  margin:16px 0;
  border:1px solid var(--border-2);
  background:var(--surface-2);
  border-radius:14px;
  padding:14px;
}
.credit-note-panel__head {
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:12px;
  margin-bottom:10px;
}
.credit-note-panel__head h4 {
  margin:0;
}
.credit-note-empty {
  color:var(--ink-muted);
  padding:12px;
  background:#fff;
  border:1px dashed var(--border-2);
  border-radius:12px;
}
.credit-note-list {
  display:grid;
  gap:8px;
}
.credit-note-row {
  display:grid;
  grid-template-columns:auto minmax(0, 1fr) 150px;
  gap:10px;
  align-items:center;
  margin:0;
  background:#fff;
  border:1px solid var(--border-2);
  border-radius:12px;
  padding:10px 12px;
}
.credit-note-row small {
  display:block;
  color:var(--ink-muted);
  margin-top:2px;
}
.credit-note-amount {
  width:100%;
}
.form-error-summary {
  background:#fff4ed;
  border:1px solid #fed7aa;
  color:#9a3412;
  border-radius:12px;
  padding:10px 12px;
  margin-bottom:12px;
}
.payment-form-error {
  grid-column: 1 / -1;
}
.form-error-summary p {
  margin:2px 0;
}
.billing-email-modal {
  max-width:1180px;
}
.billing-email-layout {
  display:grid;
  grid-template-columns:minmax(320px, 420px) minmax(420px, 1fr);
  gap:24px;
  align-items:stretch;
}
.billing-email-form {
  min-width:0;
}
.billing-email-form .avq-input {
  width:100%;
  box-sizing:border-box;
}
.billing-email-form textarea.avq-input {
  min-height:150px;
}
.billing-email-form .modal-footer {
  margin-top:28px;
}
.billing-preview-pane {
  border:1px solid var(--border-2);
  border-radius:16px;
  background:var(--surface-2);
  overflow:hidden;
  min-height:520px;
}
.billing-preview-head {
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  padding:12px 14px;
  border-bottom:1px solid var(--border-2);
  color:var(--ink-muted);
  font-size:13px;
}
.billing-preview-head a { color:var(--teal-deep); font-weight:800; text-decoration:none; }
.link-button {
  border:0;
  background:transparent;
  color:var(--teal-deep);
  font:inherit;
  font-weight:800;
  cursor:pointer;
  padding:0;
}
.billing-preview-frame {
  width:100%;
  height:480px;
  border:0;
  background:#fff;
  display:block;
}
.preview-state {
  padding:28px;
  text-align:center;
  color:var(--ink-muted);
  font-weight:700;
}
.preview-state.error { color:#b91c1c; }
.pdf-preview-modal {
  max-width:1180px;
  padding:24px;
}
.pdf-preview-title {
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:16px;
  margin-bottom:14px;
}
.pdf-preview-title h3 { margin:0; }
.pdf-preview-frame {
  width:100%;
  height:min(72vh, 760px);
  border:1px solid var(--border-2);
  border-radius:14px;
  background:#fff;
}
.pi-validation-box {
  background:#fff7ed;
  border:1px solid #fed7aa;
  color:#9a3412;
  border-radius:12px;
  padding:12px 14px;
  margin:10px 0 14px;
  font-size:13px;
}
.pi-validation-box ul { margin:8px 0 0 18px; padding:0; }
.field-error { display:block; color:#b91c1c; font-size:12px; margin-top:4px; }
.field-hint { display:block; color:var(--ink-muted); font-size:12px; margin-top:4px; }
.avq-input.is-invalid { border-color:#ef4444 !important; box-shadow:0 0 0 3px rgba(239,68,68,.12); }
.finalize-summary {
  display:grid;
  gap:8px;
  margin:16px 0;
  padding:12px;
  background:var(--surface-2);
  border:1px solid var(--border-2);
  border-radius:14px;
}
.finalize-summary div {
  display:flex;
  justify-content:space-between;
  gap:12px;
}
.finalize-summary span {
  color:var(--ink-muted);
  font-size:12px;
  font-weight:700;
}
.finalize-summary strong {
  color:var(--teal-deep);
  text-align:right;
}
.otp-info {
  padding:12px;
  border:1px solid #fed7aa;
  border-radius:12px;
  background:#fff7ed;
  color:#9a3412;
  font-size:13px;
  font-weight:700;
  line-height:1.45;
  margin:14px 0;
}
.otp-input {
  text-align:center;
  letter-spacing:.28em;
  font-weight:800;
  font-size:20px;
}
.resend-btn {
  margin-top:10px;
}
.discount-mode-panel {
  margin:18px 0;
  padding:14px;
  background:var(--surface-2);
  border:1px solid var(--border-2);
  border-radius:14px;
}
.discount-mode-options { display:flex; gap:22px; flex-wrap:wrap; align-items:center; margin-top:6px; }
.discount-mode-options label { display:flex; align-items:center; gap:7px; color:var(--ink); font-size:14px; margin:0; }
.invoice-discount-field { max-width:260px; margin-top:10px; }
.pi-items-scroll {
  width:100%;
  max-width:100%;
  overflow-x:visible;
  overflow-y:visible;
}
.pi-items-grid {
  width:100%;
}
.item-header-row,
.item-row {
  display:grid;
  grid-template-columns:minmax(210px, 1.55fr) minmax(135px, .9fr) 72px 88px 70px 48px;
  gap:10px;
  align-items:center;
}
.item-header-row {
  background:var(--surface-2);
  color:var(--ink-muted);
  font-size:11px;
  font-weight:800;
  letter-spacing:.08em;
  text-transform:uppercase;
  margin:8px 0 0;
  padding:12px;
  border-radius:10px 10px 0 0;
}
.item-row {
  padding:12px;
  border-bottom:1px solid var(--border-2);
  background:#fff;
}
.item-row:last-of-type { border-bottom:0; border-radius:0 0 10px 10px; }
.item-row .avq-input {
  height:44px;
  font-size:14px;
  padding-left:10px;
  padding-right:10px;
}
.item-row .avq-btn-sm {
  width:42px;
  height:42px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
}
.select2-combobox { position:relative; min-width:0; }
.select2-combobox__input { width:100%; }
.select2-combobox__menu {
  position:absolute;
  z-index:30;
  left:0;
  right:0;
  top:calc(100% + 6px);
  max-height:280px;
  overflow:auto;
  background:#fff;
  border:1px solid var(--teal-mid);
  border-radius:14px;
  box-shadow:0 18px 45px rgba(15, 23, 42, .16);
  padding:6px;
}
.select2-combobox__option {
  width:100%;
  display:flex;
  justify-content:space-between;
  gap:12px;
  align-items:center;
  border:0;
  background:#fff;
  border-radius:10px;
  padding:10px 12px;
  text-align:left;
  cursor:pointer;
  font:inherit;
}
.select2-combobox__option:hover { background:var(--teal-pale); }
.select2-combobox__option strong,
.select2-combobox__option small { display:block; }
.select2-combobox__option small { color:var(--ink-muted); margin-top:2px; }
.select2-combobox__option em { color:var(--teal-deep); font-style:normal; font-weight:800; white-space:nowrap; }
.select2-combobox__empty { margin:0; padding:14px; color:var(--ink-muted); text-align:center; }
textarea.avq-input { min-height:90px; resize:vertical; }
.approver-row { display:grid; grid-template-columns:240px 1fr; gap:16px; align-items:center; padding:12px 0; border-top:1px solid var(--border-2); }
.coming-soon { min-height:260px; display:flex; flex-direction:column; align-items:center; justify-content:center; }
@media (max-width: 980px) {
  .payment-form-grid { grid-template-columns:1fr; }
  .billing-email-layout { grid-template-columns:1fr; }
  .billing-preview-pane { min-height:420px; }
  .billing-preview-frame { height:380px; }
}
</style>
