@props(['emailAppearance' => []])

@php
$appearance = is_array($emailAppearance) ? $emailAppearance : [];
$outerBackgroundColor = $appearance['outerBackgroundColor'] ?? null;
$innerBackgroundColor = $appearance['innerBackgroundColor'] ?? null;
$fontFamily = $appearance['fontFamily'] ?? null;
$fontColor = $appearance['fontColor'] ?? null;

$wrapperStyle = $outerBackgroundColor ? 'background-color: ' . e($outerBackgroundColor) . ';' : '';
$bodyStyle = $outerBackgroundColor ? 'background-color: ' . e($outerBackgroundColor) . ';' : '';
$innerBodyStyle = $innerBackgroundColor ? 'background-color: ' . e($innerBackgroundColor) . ';' : '';
$fontFamilyCss = $fontFamily ? 'font-family: \'' . e($fontFamily) . '\', sans-serif;' : '';
$fontColorCss = $fontColor ? 'color: ' . e($fontColor) . ';' : '';
$contentCellStyle = ($fontFamilyCss || $fontColorCss) ? $fontFamilyCss . ' ' . $fontColorCss : '';
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    @if($fontFamily && preg_match('/^[A-Za-z0-9\s\-]+$/', $fontFamily))
    <link href="https://fonts.googleapis.com/css2?family={{ str_replace(' ', '+', $fontFamily) }}:wght@400;600;700&display=swap" rel="stylesheet">
    @endif
    <style>
        @media only screen and (max-width: 600px) {
            .inner-body {
                width: 100% !important;
            }

            .footer {
                width: 100% !important;
            }
        }

        @media only screen and (max-width: 500px) {
            .button {
                width: 100% !important;
            }
        }
    </style>
</head>

<body>

    <table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation" @if($wrapperStyle) style="{{ $wrapperStyle }}" @endif>
        <tr>
            <td align="center">
                <table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
                    {{ $header ?? '' }}

                    <!-- Email Body -->
                    <tr>
                        <td class="body" width="100%" cellpadding="0" cellspacing="0" @if($bodyStyle) style="{{ $bodyStyle }}" @endif>
                            <table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation" @if($innerBodyStyle) style="{{ $innerBodyStyle }}" @endif>
                                <!-- Body content -->
                                <tr>
                                    <td class="content-cell" @if($contentCellStyle) style="{{ $contentCellStyle }}" @endif>
                                        {{ Illuminate\Mail\Markdown::parse($slot) }}

                                        {{ $subcopy ?? '' }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{ $footer ?? '' }}
                </table>
            </td>
        </tr>
    </table>
</body>

</html>