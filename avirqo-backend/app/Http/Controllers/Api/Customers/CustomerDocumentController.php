<?php

namespace App\Http\Controllers\Api\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomerDocumentController extends Controller
{
    public function upload(Request $request, Customer $customer)
    {
        $request->validate([
            'document' => ['required', 'file', 'max:10240', // 10MB max
                'mimes:pdf,doc,docx,jpg,jpeg,png'],
        ]);

        $file = $request->file('document');
        $storedName = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('customers/'.$customer->id, $storedName, 'local');

        $doc = CustomerDocument::create([
            'customer_id'   => $customer->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_name'   => $storedName,
            'path'          => $path,
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
            'uploaded_by'   => $request->user()->id,
        ]);

        return response()->json($doc->load('uploadedBy'), 201);
    }

    public function download(Customer $customer, CustomerDocument $document)
    {
        if ($document->customer_id !== $customer->id) {
            abort(403);
        }

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroy(Customer $customer, CustomerDocument $document)
    {
        if ($document->customer_id !== $customer->id) {
            abort(403);
        }

        Storage::disk('local')->delete($document->path);
        $document->delete();

        return response()->json(['message' => 'Document deleted.']);
    }
}
