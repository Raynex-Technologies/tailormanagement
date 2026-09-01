<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Invoice preview unavailable') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #f4f4f5;
            color: #27272a;
            font-family: Arial, Helvetica, sans-serif;
        }
        main {
            max-width: 440px;
            border: 1px solid #e4e4e7;
            border-radius: 16px;
            padding: 24px;
            background: #ffffff;
            text-align: center;
        }
        h1 { margin: 0 0 8px; font-size: 20px; }
        p { margin: 0; color: #71717a; line-height: 1.5; }
    </style>
</head>
<body>
    <main role="alert">
        <h1>{{ __('Preview unavailable') }}</h1>
        <p>{{ __('This invoice template could not be rendered. Choose another template or contact an administrator.') }}</p>
    </main>
</body>
</html>
