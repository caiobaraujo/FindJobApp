<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('app.resume_variants.pdf.document_title') }}</title>
    <style>
        @page {
            margin: 36px 40px;
        }

        body {
            color: #111827;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
        }

        h1, h2, p {
            margin: 0;
        }

        .header {
            border-bottom: 1px solid #d1d5db;
            margin-bottom: 20px;
            padding-bottom: 14px;
        }

        .candidate-name {
            font-size: 20pt;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .meta-grid {
            margin-top: 10px;
        }

        .meta-row {
            margin-bottom: 4px;
        }

        .meta-label {
            color: #4b5563;
            display: inline-block;
            font-size: 9pt;
            font-weight: bold;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            width: 130px;
        }

        .section {
            margin-top: 18px;
        }

        .section-title {
            border-bottom: 1px solid #e5e7eb;
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 0.04em;
            margin-bottom: 8px;
            padding-bottom: 4px;
            text-transform: uppercase;
        }

        .section-body {
            white-space: pre-line;
        }
    </style>
</head>
<body>
    <div class="header">
        @if (filled($candidateName))
            <h1 class="candidate-name">{{ $candidateName }}</h1>
        @endif

        <div class="meta-grid">
            @if (filled($jobTitle))
                <div class="meta-row">
                    <span class="meta-label">{{ __('app.resume_variants.pdf.target_role') }}</span>
                    <span>{{ $jobTitle }}</span>
                </div>
            @endif

            @if (filled($companyName))
                <div class="meta-row">
                    <span class="meta-label">{{ __('app.resume_variants.pdf.company') }}</span>
                    <span>{{ $companyName }}</span>
                </div>
            @endif

            <div class="meta-row">
                <span class="meta-label">{{ __('app.resume_variants.pdf.mode') }}</span>
                <span>{{ $modeLabel }}</span>
            </div>
        </div>
    </div>

    @foreach ($sections as $section)
        <section class="section">
            <h2 class="section-title">{{ __("app.resume_variants.sections.{$section['key']}") }}</h2>
            <div class="section-body">{{ $section['body'] }}</div>
        </section>
    @endforeach
</body>
</html>
