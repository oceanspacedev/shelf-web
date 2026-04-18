<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', 'Google Sans', Arial, sans-serif;
            background-color: #f0f4f9;
            margin: 0;
            padding: 30px 16px;
            color: #202124;
            line-height: 1.5;
        }

        .wrapper {
            max-width: 640px;
            margin: 0 auto;
        }

        .header-card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #dadce0;
            margin-bottom: 12px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        .header-card-top {
            background-color: #1a73e8;
            height: 10px;
            width: 100%;
        }

        .header-content {
            padding: 24px;
        }

        h1 {
            font-size: 32px;
            font-weight: 400;
            margin: 0 0 12px 0;
            color: #202124;
            letter-spacing: -0.2px;
        }

        .subtitle {
            font-size: 14px;
            color: #5f6368;
            margin: 0;
        }

        .header-separator {
            border: 0;
            border-top: 1px solid #dadce0;
            margin: 16px -24px 16px;
        }

        .required-text {
            color: #d93025;
            font-size: 13px;
            margin: 0;
        }

        .card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #dadce0;
            padding: 24px;
            margin-bottom: 12px;
            position: relative;
            transition: box-shadow 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .card:focus-within {
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        label {
            display: flex;
            align-items: center;
            font-size: 15px;
            font-weight: 500;
            color: #202124;
            margin-bottom: 8px;
        }

        .required {
            color: #d93025;
            margin-left: 4px;
        }

        input[type="text"], input[type="number"], input[type="email"], select, textarea {
            width: 100%;
            padding: 10px 14px;
            font-size: 14px;
            border: 1px solid #dadce0;
            border-radius: 4px;
            background-color: #fff;
            outline: none;
            box-sizing: border-box;
            color: #202124;
            font-family: inherit;
            transition: all 0.2s;
        }

        input[type="text"]::placeholder, input[type="number"]::placeholder, input[type="email"]::placeholder {
            color: #9aa0a6;
        }

        input[type="text"]:focus, input[type="number"]:focus, input[type="email"]:focus, select:focus, textarea:focus {
            border-color: #1a73e8;
            box-shadow: 0 0 0 1px #1a73e8;
        }

        .hint {
            color: #5f6368;
            font-size: 12px;
            margin-top: 6px;
        }

        .button-group {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 24px;
            margin-bottom: 32px;
        }

        .page-indicator {
            font-size: 13px;
            color: #5f6368;
        }

        .form-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .button {
            background-color: #1a73e8;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .button:hover:not(:disabled) {
            background-color: #1557b0;
        }
        
        .button:disabled, .next:disabled, .back:disabled {
            background-color: #f1f3f4;
            color: #bdc1c6;
            cursor: not-allowed;
            box-shadow: none;
            border-color: transparent;
        }

        .back, .next {
            color: #1a73e8;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 9px 23px;
            border-radius: 6px;
            border: 1px solid #dadce0;
            transition: background-color 0.2s;
            background-color: #fff;
            display: inline-block;
        }

        .back:hover, .next:hover {
            background-color: #f8f9fa;
        }

        .alert {
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .alert-success {
            background-color: #e6f4ea;
            color: #137333;
            border: 1px solid #ceead6;
        }

        .alert-error {
            background-color: #fce8e6;
            color: #c5221f;
            border: 1px solid #fad2cf;
        }

        .alert-info {
            background-color: #e8f0fe;
            color: #1a73e8;
            border: 1px solid #c6dafc;
        }

        /* Styles specific for index page radio-like choices */
        .radio-option {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
            cursor: pointer;
        }
        
        .radio-option:last-child {
            margin-bottom: 0;
        }

        .radio-circle {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #5f6368;
            margin-right: 12px;
            transition: border-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .radio-option:hover .radio-circle {
            border-color: #202124;
        }

        .radio-inner-circle {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: transparent;
            transition: background-color 0.2s;
        }
        
        .radio-option.selected .radio-circle {
            border-color: #1a73e8;
        }
        
        .radio-option.selected .radio-inner-circle {
            background-color: #1a73e8;
        }
        
        .radio-label {
            font-size: 14px;
            color: #202124;
        }
        
        .radio-option a {
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            width: 100%;
        }

        @yield('styles')
    </style>
</head>

<body>
    <main class="wrapper">
        <div class="header-card">
            <div class="header-card-top"></div>
            <div class="header-content">
                <h1>@yield('header_title')</h1>
                <p class="subtitle">@yield('header_subtitle')</p>
                @hasSection('show_required')
                    <hr class="header-separator">
                    <p class="required-text">* Required</p>
                @endif
            </div>
        </div>

        @if (session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        @if (session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
        @endif

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error">
                {{ $errors->first() }}
            </div>
        @endif

        @yield('content')
    </main>
</body>

</html>
