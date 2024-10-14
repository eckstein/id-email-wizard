    <?php
    // Font Styles
    $templateFontSize = $templateStyles['font-styles']['template_font_size'] ?? '16px';
    $templateLineHeight = $templateStyles['font-styles']['template_line_height'] ?? '1.5';

    // Link Styles
    $linkColor = $templateStyles['link-styles']['template_link_style_color'] ?? '#1e73be';
    $linkHoverColor = $templateStyles['link-styles']['template_link_style_hover_color'] ?? $linkColor;

    $linkStyles = $templateStyles['link-styles'];
    $underlineLinks = json_decode($linkStyles['underline'] ?? 'false');
    $boldLinks = json_decode($linkStyles['bold'] ?? 'false');
    $italicLinks = json_decode($linkStyles['italic'] ?? 'false');

    // Construct link styles
    $linkStylesArray = [];
    if ($linkColor) {
        $linkStylesArray[] = "color: $linkColor";
    }
    $linkStylesArray[] = $underlineLinks ? "text-decoration: underline" : "text-decoration: none";
    if ($boldLinks) {
        $linkStylesArray[] = "font-weight: bold";
    }
    if ($italicLinks) {
        $linkStylesArray[] = "font-style: italic";
    }
    $linkStylesString = implode('; ', $linkStylesArray);

    $underlineStyle = $underlineLinks ? "text-decoration: underline" : "text-decoration: none";

    $darkModeSupport = $templateStyles['custom-styles']['dark-mode-support'] === true ? true : false;

    $textStyles = $templateStyles['text-styles'] ?? [];
    $baseTextColor = $textStyles['text_styles_text_color'];
    $baseDarkModeTextColor = $textStyles['text_styles_dark_mode_text_color'];

    $darkModeCss = '';
    if ($darkModeSupport) {
        $darkModeCss = <<<HTML
        <style type="text/css">
            .dark-image, .dark-image img {
                display: none;
            }
        @media (prefers-color-scheme: dark ) {
            .light-image {
                display:none!important
            }
            .dark-image {
                display:inline!important
            }
            p,h1,h2,h3,h4,h5,h6 {
            color: {$baseDarkModeTextColor};
            }
        }
        
        [data-ogsc] .light-image,
        [data-ogsb] .light-image {
                display:none!important
            }
        [data-ogsc] .dark-image,
        [data-ogsb] .dark-image {
                display:inline!important
            }
        [data-ogsc] p,[data-ogsc] h1,[data-ogsc] h2,[data-ogsc] h3,[data-ogsc] h4,[data-ogsc] h5,[data-ogsc] h6 {
            color: #fff;
            }
        [data-ogsb] p,[data-ogsb] h1,[data-ogsb] h2,[data-ogsb] h3,[data-ogsb] h4,[data-ogsb] h5,[data-ogsb] h6 {
            color: #fff;
            }
            
        </style>
        HTML;
    }

    $css = <<<HTML
    <!-- The first style block will be removed by Yahoo! on android, so nothing here for that platform (we'll use it for gmail) -->
    <!--dedicated block for gmail-->
    <style type="text/css">
        u + .body a {
            color: {$linkColor};
            {$underlineStyle};
            font-size: inherit;
            font-family: inherit;
            font-weight: inherit;
            line-height: inherit;
        }
    </style>

    <style type="text/css">
        @media screen and (min-width: 481px) {
            u ~ .body .gmail-blend-screen.desktop {
                background: #000;
                mix-blend-mode: screen;
            }

            u ~ .body .gmail-blend-difference.desktop {
                background-color: #000;
                mix-blend-mode: difference;
            }
        }

        @media screen and (max-width: 480px) {
            u ~ .body .gmail-blend-screen.mobile {
                background: #000;
                mix-blend-mode: screen;
            }

            u ~ .body .gmail-blend-difference.mobile {
                background-color: #000;
                mix-blend-mode: difference;
            }
        }
    </style>

    <style type="text/css">
        /* Prevent auto-blue links in Apple */
        a[x-apple-data-detectors] {
            color: {$linkColor} !important;
            {$underlineStyle};
            font-size: inherit !important;
            font-family: inherit !important;
            font-weight: inherit !important;
            line-height: inherit !important;
        }

        /* Prevent blue links in Samsung */
        #MessageViewBody a {
            color: {$linkColor} !important;
            {$underlineStyle};
            font-size: inherit !important;
            font-family: inherit !important;
            font-weight: inherit !important;
            line-height: inherit !important;
        }
    </style>

    
    <style type="text/css">
        /* Global styles for all clients that can read them */
        html, body, article {
            color: {$baseTextColor};
        }
        html * {
            font-family: 'Poppins', Helvetica, Arial, sans-serif;
        }

        #outlook a {
            padding: 0;
            color: {$linkColor};
        }

        .ReadMsgBody {
            width: 100%;
        }

        .ExternalClass {
            width: 100%;
        }

        .ExternalClass,
        .ExternalClass p,
        .ExternalClass span,
        .ExternalClass font,
        .ExternalClass td,
        .ExternalClass div {
            line-height: 100%;
        }

        body {
            font-size: {$templateFontSize};
            line-height: {$templateLineHeight};
            font-family: 'Poppins', Helvetica, Arial, sans-serif;
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }

        body,
        table,
        td,
        a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            
        }
        a {
            color: {$linkColor};
        }

    table,
    td {
        mso-table-lspace: 0pt;
        mso-table-rspace: 0pt;
    }

    img {
        -ms-interpolation-mode: bicubic;
        border: 0;
        height: auto;
        line-height: 100%;
        outline: none;
        text-decoration: none;
    }

    p {
        margin: 0;
        padding: 0 0 1em 0;
        color: inherit;
    }

    .noPpad p {
        padding: 0 !important;
    }


    /* Desktop Headers */
    h1 {
        margin: 0 !important;
        padding: 0 0 .67em 0;
        font-size: 2em !important;
        color: inherit;
    }

    h2 {
        margin: 0 !important;
        padding: 0 0 .83em 0;
        font-size: 1.5em !important;
        color: inherit;
    }

    h3 {
        margin: 0 !important;
        padding: 0 0 1em 0;
        font-size: 1.17em !important;
        color: inherit;
    }

    h4 {
        margin: 0 !important;
        padding: 0 0 1.33em 0;
        font-size: 1em !important;
        color: inherit;
    }

    h5 {
        margin: 0 !important;
        padding: 0 0 1.67em 0;
        font-size: .83em !important;
        color: inherit;
    }

    h6 {
        margin: 0 !important;
        padding: 0 0 1.33em 0;
        font-size: .67em !important;
        color: inherit;
    }

    .noPad h1,
    .noPad h2,
    .noPad h3,
    .noPad h4,
    .noPad h5,
    .noPad h6 {
        padding: 0 !important;
    }

    a,
    a:visited {
        {$linkStylesString};
    }

    a:hover {
        color: {$linkHoverColor};
    }

    a.id-button {
        text-decoration: none !important;
        font-style: normal !important;
        font-weight: bold;
        color: inherit;
    }

    a.id-image-link {
        color: transparent;
        text-decoration: none !important;
        line-height: 0;
        font-size: 0;
    }

    ul {
        margin-top: 0;
        margin-block-start: 0;
    }

    ul>li {
        line-height: 1.5;
        padding-bottom: .7em;
    }

    table,
    td {
        margin: 0;
        padding: 0;
        font-size: inherit;
        line-height: inherit;
        font-family: 'Poppins', Helvetica, Arial, sans-serif;
    }

    .columnSet {
       white-space: nowrap;
       display: flex;
       flex-wrap: wrap;
    }
    .column {
        white-space: normal;
    }

    @media screen and (max-width: 480px) {
        .columnSet.noWrap {
            white-space: nowrap;
        }

        /* Mobile Headers */
        h1 {
            margin: 0 0 .83em 0;
            font-size: 1.5em !important;
        }

        h2 {
            margin: 0 0 .9em 0;
            font-size: 1.3em !important;
        }

        h3 {
            margin: 0 0 1em 0;
            font-size: 1.17em !important;
        }

        h4 {
            margin: 0 0 1.33em 0;
            font-size: 1em !important;
        }

        h5 {
            margin: 0 0 1.67em 0;
            font-size: .83em !important;
        }

        h6 {
            margin: 0 0 1.33em 0;
            font-size: .67em !important;
        }
    }
</style>

<!-- If this is a non-MSO windows client, we include styles for dynamic mobile/desktop visibility and columns -->
<!--[if !mso]><!-->

<style type="text/css">
    
    .desktop-only {
        display: block;
        /* Show by default */
    }

    table.desktop-only {
        display: table;
    }

    .mobile-only {
        display: none;
        /* Hide by default */
    }

    @media screen and (max-width: 480px) {

        .three-col .column.wrap,
        .two-col .column.wrap,
        .sidebar-left .column.wrap,
        .sidebar-right .column.wrap {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 100% !important;
            display: block !important;
            overflow: hidden;
        }

        .mobile-only {
            display: block !important;
        }

        table.mobile-only {
            display: table !important;
        }

        .desktop-only {
            display: none !important;
        }
    }
    @media screen and (min-width: 481px) {
        .three-col .column {
            max-width: 33.333% !important;
            min-width: 33.333% !important;
            display: inline-block;
            text-align: left;
            overflow: hidden;
        }

        .two-col .column {
            max-width: 50% !important;
            min-width: 50% !important;
            display: inline-block;
            text-align: left;
            overflow: hidden;
        }


        .desktop-only {
            display: block !important;
        }

        table.desktop-only {
            display: table !important;
        }

        .mobile-only {
            display: none !important;
        }
    }

    
</style>
<!--<![endif]-->

<!-- Include dark mode CSS -->
{$darkModeCss}

<!-- MSO app only styles-->
<!--[if mso]>
    <style type="text/css">
        table {
            border-collapse: collapse;
            border: 0;
            border-spacing: 0;
            margin: 0;
            mso-table-lspace: 0pt !important;
            mso-table-rspace: 0pt !important;
        }
        div,
        td {
            padding: 0;
        }
        div,p {
            margin: 0 !important;
        }
				
        .desktop-only {
            display: block; /* Show by default */
        }
        table.desktop-only {
            display: table;
        }

        .mobile-only {
            display: none!important; /* Hide by default */
        }

        .column {
            width: 100%!important;
            max-width: 100%!important;
            min-width: 100%!important;
            display: table-cell !important;            
        }
    </style>
			
<![endif]-->

HTML;

echo $css;