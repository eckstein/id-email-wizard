<?php
$dtSize = $templateStyles['desktop_font_size'] ?? '18px';
$dtHeight = $templateStyles['desktop_line_height'] ?? '26px';
$mobSize = $templateStyles['mobile_font_size'] ?? '16px';
$mobHeight = $templateStyles['mobile_line_height'] ?? '24px';
$linkColor = $templateStyles['link_color'] ?? '#94D500';
$visLinkColor = $templateStyles['visited_link_color'] ?? '#94d500';
$linkUnderline = $templateStyles['underline_links'] ?? true;
$templateBgColor = $templateStyles['template_bg_color'] ?? '#F4F4F4';
?>
<!--[if (gte mso 9)|(IE)]>
    <style>
      :root {
      color-scheme: light only;
      }
      table,tr,td,div,span,p,a {
      font-family:Arial,Helvetica,sans-serif !important;
      }
      td a {
      color:inherit !important;
      text-decoration:none !important;
      }
      a {
      color:inherit !important;
      text-decoration:none !important;
      }
    </style>
    <![endif]-->
<!-- Our main styles, which will be inlined by Iterable upon send -->
<!-- Style block is wrapped in CDATA, which tells the code interpretor that it should not try to interpret the data enclosed in the tags (because it's a different language)-->
<style type="text/css">
  /*
        <![CDATA[*/
  /*Basics*/
  body {
    margin: 0 auto !important;
    padding: 0 !important;
    display: block !important;
    min-width: 100% !important;
    width: 100% !important;
    -webkit-text-size-adjust: none;
    font-family: Poppins, sans-serif;
  }

  table {
    border-spacing: 0;
    mso-table-lspace: 0;
    mso-table-rspace: 0;
  }

  table td {
    border-collapse: collapse;
  }

  strong {
    font-weight: bold !important;
  }

  a {
    color:
      <?php echo $linkColor; ?>
    ;
    <?php if ($linkUnderline) {
      echo 'text-decoration: underline;';
    } else {
      echo 'text-decoration: none;';
    } ?>
  }

  a:visited {
    color:
      <?php echo $visLinkColor; ?>
    ;
  }

  td img {
    -ms-interpolation-mode: bicubic;
    display: block;
    width: auto;
    max-width: auto;
    height: auto;
    margin: auto;
  }

  td p {
    margin: 0 0 1em 0 !important;
    padding: 0 !important;
    font-family: inherit !important;
  }

  td ul {
    margin-top: .5em;
  }

  td ul li {
    margin-bottom: .5em;
  }

  /*Outlook*/
  .ExternalClass {
    width: 100%;
  }

  .ExternalClass,
  .ExternalClass p,
  .ExternalClass span,
  .ExternalClass font,
  .ExternalClass td,
  .ExternalClass div {
    line-height: inherit;
  }

  .ReadMsgBody {
    width: 100%;
    background-color: #ffffff;
  }

  /* iOS BLUE LINKS */
  a[x-apple-data-detectors] {
    color: inherit !important;
    text-decoration: none !important;
    font-size: inherit !important;
    font-family: inherit !important;
    font-weight: inherit !important;
    line-height: inherit !important;
  }

  /*Gmail blue links*/
  u+#body a {
    color: inherit;
    text-decoration: none;
    font-size: inherit;
    font-family: inherit;
    font-weight: inherit;
    line-height: inherit;
  }

  /*Buttons fix*/
  .undoreset a,
  .undoreset a:hover {
    text-decoration: none !important;
  }

  .yshortcuts a {
    border-bottom: none !important;
  }

  .ios-footer a {
    color: #aaaaaa !important;
    text-decoration: none;
  }

  /*Images*/
  td.img600 img {
    width: 100%;
    max-width: 600px;
  }

  td.img800 img {
    width: 100%;
    max-width: 800px;
  }

  .hide-desktop {
    height: 0;
    overflow: hidden;
    display: none;
  }
   



  /*Responsive*/
  /*Hide and show mobile and desktop versions*/
  /*Desktop Only Style*/
  @media screen and (min-width: 661px) {
    .hide-desktop {
      display: none !important;
      height: 0 !important;
      overflow: hidden !important;
    }

    .hide-mobile {
      display: table !important;
      height: auto !important;
    }

    .responsive-text,
    .responsive-text p {
      font-size:
        <?php echo $dtSize; ?>
        !important;
      line-height:
        <?php echo $dtHeight; ?>
        !important;
    }
    .responsive-text.add-padding {
      padding: 40px;
    }

    .id-button {
      padding: 14px 22px;
      font-size: 18px;
    }
    .email-icon-cell {
      width: 16.66%;
    }
  }

  /*Mobile Only Style*/
  @media screen and (max-width: 660px) {
    .hide-mobile {
      display: none !important;
      height: 0 !important;
      overflow: hidden !important;
    }

    .center-on-mobile {
      text-align: center !important;
    }

    .hide-desktop {
      display: table !important;
      height: auto !important;
    }


    .id-button {
      padding: 12px 16px;
      font-size: 16px;
    }
    .email-icon-cell {
      width: 50%;
      display: inline-block;
      box-sizing: border-box;
    }
    .items-row-wrapped tr td {
    display: inline-block;
    width: 80px!important;
    }

    .responsive-text,
    .responsive-text p {
      font-size:
        <?php echo $mobSize; ?>
        !important;
      line-height:
        <?php echo $mobHeight; ?>
        !important;
      
    }
    .responsive-text.add-padding {
      padding-left: 0!important;
      padding-right: 0!important;
    }

    .center-on-mobile {
      text-align: center !important;
    }

    .colGap {
      display: none;
    }

    td.img-responsive img {
      width: 100% !important;
      max-width: 100% !important;
      height: auto !important;
      margin: auto;
    }

    #MessageViewBody,
    #MessageWebViewDiv {
      width: 100% !important;
    }

    table.row {
      width: 100% !important;
      max-width: 100% !important;
    }

    table.center-float,
    td.center-float {
      float: none !important;
    }

    td.center-text {
      text-align: center !important;
    }

    td.container-padding {
      width: 100% !important;
      padding-left: 15px !important;
      padding-right: 15px !important;
    }

    td.menu-container {
      text-align: center !important;
    }

    td.autoheight {
      height: auto !important;
    }

    td.height200 {
      height: 200px !important;
    }

    td.border-rounded {
      border-radius: 6px !important;
    }

    td.border-none {
      border: none !important;
    }

    table.mobile-padding {
      margin: 15px 0 !important;
    }
  }

  body {
    background-color: <?php echo $templateBgColor; ?> !important;
  }

  /*]]>*/
</style>