<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 24px;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
    <tr>
        <td style="background:#333;padding:24px;text-align:center;">
            <h1 style="color:#fff;margin:0;font-size:1.4em;"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
        </td>
    </tr>
    <tr>
        <td style="padding:32px 24px;">
            {{email_body}}
        </td>
    </tr>
    <tr>
        <td style="background:#f5f5f5;padding:16px 24px;text-align:center;font-size:0.8em;color:#999;">
            &copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?>
        </td>
    </tr>
</table>
</body>
</html>
