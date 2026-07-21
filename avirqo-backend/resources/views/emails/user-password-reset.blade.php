<!DOCTYPE html>
<html>
<body style="font-family: 'DM Sans', Arial, sans-serif; color: #0D0D0C; max-width: 640px; margin: 0 auto; padding: 24px; background: #f8f9fa;">
  <div style="background: #fff; border-radius: 16px; padding: 28px; border: 1px solid #E4EDE9;">
    <div style="font-size: 22px; font-weight: 700; color: #085041; margin-bottom: 8px;">
      {{ $isNewUser ? 'Welcome to Avirqo' : 'Your password has been reset' }}
    </div>
    <p style="margin: 0 0 18px; color: #6B6A67; line-height: 1.6;">
      Hi {{ $user->name }},<br>
      {{ $isNewUser ? 'Your account has been created.' : 'Your account password has been updated.' }}
    </p>
    <div style="background: #F7FAF9; border: 1px solid #E4EDE9; border-radius: 12px; padding: 16px 18px; margin-bottom: 18px;">
      <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: #6B6A67; margin-bottom: 8px;">Login Details</div>
      <table style="width: 100%; border-collapse: collapse;">
        <tr>
          <td style="padding: 4px 0; color: #6B6A67;">Email</td>
          <td style="padding: 4px 0; text-align: right; font-weight: 600;">{{ $user->email }}</td>
        </tr>
        <tr>
          <td style="padding: 4px 0; color: #6B6A67;">Temporary Password</td>
          <td style="padding: 4px 0; text-align: right; font-weight: 700; color: #085041;">{{ $plainPassword }}</td>
        </tr>
      </table>
    </div>
    <p style="color: #92400E; font-size: 13px; line-height: 1.6; background: #FFFBEB; border: 1px solid #FDE68A; padding: 12px 14px; border-radius: 8px;">
      Please sign in and change your password after login.
    </p>
  </div>
</body>
</html>
