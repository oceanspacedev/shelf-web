<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <base target="_top">
</head>

<body
  style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; line-height: 1.5; color: #111827;">
  <table cellpadding="0" cellspacing="0" width="100%"
    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <tr>
      <td width="100%" style="background: #eee; border-radius: 3px;">
        <h1
          style="border-radius: 3px 3px 0px 0px; background: #1d4ed8; color: #fff; text-align: center; padding: 24px 0px; margin: 0px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 22px; font-weight: 700; line-height: 1.3;">
          {{ $formTitle }}
        </h1>
        <div
          style="padding: 0px 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
          <p style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
            {{ $intro }}</p>

          <table
            style="width: 100%; margin: 13px 0px; border-collapse: collapse; border-radius: 3px; overflow: hidden; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 14px;">
            @foreach ($fields as $label => $value)
              <tr>
                <td
                  style="padding: 6px 12px; border: 1px solid #333; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                  {{ $label }}</td>
                <td
                  style="padding: 6px 12px; border: 1px solid #333; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                  {{ $value }}</td>
              </tr>
            @endforeach
          </table>

              @if (!empty($approvals))
            <p style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
              Detail persetujuan</p>
            <table
              style="width: 100%; margin: 13px 0px; border-collapse: collapse; border-radius: 3px; overflow: hidden; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 14px;">
              <tr>
                <td
                  style="padding: 6px 12px; border: 1px solid #333; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                  Penyetuju</td>
                <td
                  style="padding: 6px 12px; border: 1px solid #333; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                  Jabatan</td>
                <td
                  style="padding: 6px 12px; border: 1px solid #333; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                  Status</td>
                <td
                  style="padding: 6px 12px; border: 1px solid #333; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                  Catatan</td>
                <td
                  style="padding: 6px 12px; border: 1px solid #333; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                  Waktu</td>
              </tr>
              @foreach ($approvals as $approval)
                <tr>
                  <td
                    style="padding: 6px 12px; border: 1px solid #333; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                    {{ $approval['approver'] }}</td>
                  <td
                    style="padding: 6px 12px; border: 1px solid #333; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                    {{ $approval['title'] }}</td>
                  <td
                    style="padding: 6px 12px; border: 1px solid #333; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                    {{ $approval['status'] }}</td>
                  <td
                    style="padding: 6px 12px; border: 1px solid #333; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                    {{ $approval['comments'] }}</td>
                  <td
                    style="padding: 6px 12px; border: 1px solid #333; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                    {{ $approval['timestamp'] }}</td>
                </tr>
              @endforeach
            </table>
          @endif

          <table style="width: 100%;" cellpadding="0" cellspacing="0">
            <tr>
              <td style="text-align: left;">
                <a href="{{ $ctaUrl }}" target="_blank"
                  style="border-radius: 3px; border: none; display: inline-block; text-decoration: none; padding: 12px 24px; background: {{ $ctaVariant === 'approve' ? '#34A853' : '#F59E0B' }}; color: #fff; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 14px; font-weight: 600;">
                  {{ $ctaLabel }}
                </a>
              </td>
            </tr>
          </table>

          <p style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
            <small style="color: #333;"><a href="{{ route('public.asset-requests.index') }}">Ajukan aset
                lain</a></small><br>
            <small style="color: #333;">Dikirim oleh <a href="https://completeselular.co.id/">IT Support</a></small>
          </p>
        </div>
      </td>
    </tr>
  </table>
</body>

</html>
