<?php

use AmeliaBooking\Application\Services\Helper\HelperService;
use AmeliaBooking\Infrastructure\WP\Translations\BackendStrings;

/** @var HelperService $helperService */
$helperService = $this->container->get('application.helper.service');

$data = $invoiceData['placeholders'];

$locale = get_locale();
$localePrefix = substr($locale, 0, 2);

$googleFontCss = null;
switch ($localePrefix) {
    case 'ja':
        $googleFontCss = 'https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700&display=swap';
        $fontFamily = "'Noto Sans JP', 'DejaVu Sans', sans-serif";
        break;
    case 'ko':
        $googleFontCss = 'https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;700&display=swap';
        $fontFamily = "'Noto Sans KR', 'DejaVu Sans', sans-serif";
        break;
    case 'th':
        $googleFontCss = 'https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;700&display=swap';
        $fontFamily = "'Noto Sans Thai', 'DejaVu Sans', sans-serif";
        break;
    case 'zh':
        $googleFontCss = 'https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;700&display=swap';
        $fontFamily = "'Noto Sans SC', 'DejaVu Sans', sans-serif";
        break;
    default:
        $fontFamily = "'DejaVu Sans', sans-serif";
}

?>
<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <?php if (!empty($googleFontCss)) : ?>
        <link rel="stylesheet" href="<?php echo $googleFontCss; ?>">
    <?php endif; ?>
  <style>
      body { margin: 0; font-family: <?php echo $fontFamily; ?>; line-height: 15px; }
      html { margin: 0; font-family: <?php echo $fontFamily; ?>; line-height: 15px;}
      @page { margin: 0; font-family: <?php echo $fontFamily; ?>; line-height: 15px;}

      .invoice-header {
          background-color: #F5F5F5;
          padding: 44px;
      }

      .invoice-header-first-line {
          margin-bottom: 10px;
          display: block
      }

      .invoice-header-first-line table {
          float: right;
      }

      .invoice-header-first-line table tr td:first-child {
          vertical-align: middle;
          padding-right: 16px
      }

      .invoice-header img {
          width: 50px;
          height: 50px;
      }

      .invoice-header-first-line-company-name {
          vertical-align: middle;
          font-weight: 700;
          font-size: 16px;
          color: #1A2C37
      }

      .invoice-header h2 {
          float:left;
          color: #0E1920;
          font-size: 22px
      }

      .invoice-header-second-line {
          padding-bottom: 8px
      }

      .invoice-header-table {
          width: 100%;
          font-size: 14px;
          font-weight: 400;
          color: #1A2C37;
          border-spacing: 0 2px;
      }

      .invoice-header-table-company {
          text-align: right;
      }

      .invoice-header-table-company-name {
          font-size: 16px;
      }

      .invoice-header-table-invoice-number-value, .invoice-header-table-invoice-issued-date {
          text-align: right;
          padding-left: 8px
      }

      .invoice-body {
          padding: 0 44px;
          margin-top: 25px
      }

      .invoice-body-customer {
          width: 49%;
          color: #1A2C37;
          margin-bottom: 35px;
          float: left;
      }

      .invoice-body-customer td {
          font-size: 14px;
          font-weight: 400;
          padding-bottom: 5px;
      }

      .invoice-body-customer tr td:first-child {
          text-align: left;
      }

      .invoice-body-customer-to td {
          font-weight: bold;
          padding-bottom: 10px
      }

      .invoice-body-cf {
          width: 49%;
          color: #1A2C37;
          margin-bottom: 35px;
          float: right;
          text-align: right;
      }

      .invoice-body-cf tr:first-child td {
        font-weight: bold;
        padding-bottom: 10px
      }

      .invoice-body-cf td {
          font-size: 14px;
          font-weight: 400;
          padding-bottom: 5px;
      }

      .invoice-body-items {
          width: 100%;
          margin-bottom: 20px;
          clear: both;
      }

      .invoice-body-items td {
          padding: 10px;
          font-weight: 400;
          font-size: 12px;
      }

      .invoice-body-items tr td {
          text-align: right;
      }

      .invoice-body-items tr td:first-child {
          text-align: left;
      }

      .invoice-body-items-name {
          background-color: #F5F5F5;
          color: #808A90;
      }

      .invoice-body-items-data {
          color: #1A2C37;
      }

      .invoice-body-items-data td {
          border-bottom: 1px dashed lightgray;
      }

      .invoice-body-sum {
          float: right;
          width: 50%;
          font-size: 13px;
          font-weight: 400;
          color: #1A2C37
      }

      .invoice-body-sum > div {
          margin-bottom: 10px;
      }

      .invoice-body-sum > div > span:nth-child(2) {
          float: right;
      }

      .invoice-body-total {
          font-size: 16px;
          margin-bottom: 20px;
      }

      .invoice-body-sum hr {
          color:lightgray;
      }

      .invoice-footer {
          padding: 44px;
          background-color: #F5F5F5;
          position: absolute;
          bottom: 0;
          width: 100%;
      }

      .invoice-footer-info {
          font-weight: bolder;
          font-size: 12px;
          color: #808A90;
          margin-bottom: 10px
      }

      .invoice-footer-method > div:first-child {
          font-weight: bolder;
          font-size: 11px;
          color: #1A2C37;
          margin-bottom: 5px
      }

      .invoice-footer-method > div:nth-child(2) {
          font-weight: bold;
          font-size: 12px;
          color: #808A90;
      }

  </style>
  <title><?php echo BackendStrings::get('invoice') ?></title>
</head>
<body>
<div class="invoice">

    <div class="invoice-header">

        <div class="invoice-header-first-line">
            <table class="invoice-header-first-line-company">
                <tr>$
                    <?php if (!empty($data['company_name']) && $invoiceData['companyLogo']) : ?>
                        <td class="invoice-header-first-line-company-name">
                            <span><b><?php echo $data['company_name'] ?></b></span>
                        </td>
                    <?php endif; ?>
                    <?php if ($invoiceData['companyLogo']) : ?>
                        <td>
                            <img src='data:<?php echo $invoiceData['companyLogo']['mime']; ?>;base64, . <?php echo $invoiceData['companyLogo']['img']; ?>'
                                 alt="logo">
                        </td>
                    <?php endif; ?>
                </tr>
            </table>

            <h2>
                <?php echo BackendStrings::get('invoice') ?>
            </h2>

        </div>

        <br><br><br><br>

        <div class="invoice-header-second-line">

            <table class="invoice-header-table">
                <tr>
                    <td class="invoice-header-table-invoice-number">
                        <span class="invoice-header-table-invoice-number-label"><?php echo BackendStrings::get('invoice') ?></span>
                        <span class="invoice-header-table-invoice-number-value">#<?php echo str_pad($data['invoice_number'], 4, '0', STR_PAD_LEFT) ?></span>
                    </td>
                    <td class="invoice-header-table-company invoice-header-table-company-name">
                        <span><?php echo $data['company_name'] ?></span>
                    </td>
                </tr>
                <tr>
                    <td class="invoice-header-table-invoice-issued">
                        <span class="invoice-header-table-invoice-issued-label"><?php echo BackendStrings::get('issued') ?></span>
                        <span class="invoice-header-table-invoice-issued-date"><?php echo $data['invoice_issued'] ?></span>
                    </td>
                    <td class="invoice-header-table-company">
                        <?php if (!empty($data['company_address'])) : ?>
                            <span><?php echo $data['company_address']?></span>
                        <?php elseif (!empty($data['company_email']) || !empty($data['company_phone'])) : ?>
                            <?php echo !empty($data['company_email']) ? $data['company_email'] : ''?>
                            <?php if (!empty($data['company_email']) && !empty($data['company_phone'])) : ?>
                                |
                            <?php endif; ?>
                            <?php echo !empty($data['company_phone']) ? $data['company_phone'] : ''?>
                        <?php elseif (!empty($data['company_vat_number'])) : ?>
                            <?php echo $data['company_vat_number'] ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ((!empty($data['company_email']) || !empty($data['company_phone'])) && !empty($data['company_address'])) : ?>
                    <tr>
                        <td></td>
                        <td class="invoice-header-table-company">
                            <?php echo !empty($data['company_email']) ? $data['company_email'] : ''?>
                            <?php if (!empty($data['company_email']) && !empty($data['company_phone'])) : ?>
                                 |
                            <?php endif; ?>
                            <?php echo !empty($data['company_phone']) ? $data['company_phone'] : ''?>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($data['company_vat_number']) && (!empty($data['company_address']) || !empty($data['company_email']) || !empty($data['company_phone']))) : ?>
                    <tr>
                        <td></td>
                        <td class="invoice-header-table-company">
                            <?php echo $data['company_vat_number'] ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

    </div>

    <div class="invoice-body">
        <table class="invoice-body-customer">
            <tr class="invoice-body-customer-to">
                <td><?php echo BackendStrings::get('to_upper') ?></td>
            </tr>
            <tr>
                <td class="invoice-body-customer-name"><?php echo $data['customer_full_name'] ?></td>
            </tr>
            <tr>
                <td class="invoice-body-customer-email"><?php echo !empty($data['customer_email']) ? $data['customer_email'] : ' ' ?></td>
            </tr>
            <tr>
                <td class="invoice-body-customer-phone"><?php echo !empty($data['customer_phone']) ? $data['customer_phone'] : ' ' ?></td>
            </tr>
        </table>

        <?php if (!empty($data['customer_custom_fields'])) : ?>
            <table class="invoice-body-cf">
                <tr>
                    <td><?php echo BackendStrings::get('invoice_additional_information') ?></td>
                </tr>
                <?php foreach ($data['customer_custom_fields'] as $key => $customerCustomField) : ?>
                    <tr>
                        <td><?php echo $customerCustomField ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>


        <table class='invoice-body-items'>
            <thead class="invoice-body-items-name">
            <tr>
                <td><?php echo BackendStrings::get('invoice_item'); ?></td>
                <td><?php echo BackendStrings::get('invoice_unit_price') .
                        ($invoiceData['includedTax'] ? BackendStrings::get('invoice_incl_tax') : ''); ?></td>
                <td><?php echo BackendStrings::get('invoice_qty'); ?></td>
                <td><?php echo BackendStrings::get('invoice_total_item_price') .
                        ($invoiceData['includedTax'] ? BackendStrings::get('invoice_incl_tax') : ''); ?></td>
                <?php if ($invoiceData['taxEnabled']) : ?>
                    <td><?php echo BackendStrings::get('invoice_tax_rate'); ?></td>
                    <td><?php echo BackendStrings::get('invoice_tax_amount'); ?></td>
                <?php endif; ?>
            </tr>
            </thead>
            <?php
            foreach ($invoiceData['items'] as $key => $appData) : ?>
                <tr class="invoice-body-items-data">
                    <td><?php echo $appData['item_name'] ?></td>
                    <td><?php echo $helperService->getFormattedPrice($appData['invoice_unit_price']) ?></td>
                    <td><?php echo $appData['invoice_qty'] ?></td>
                    <td><?php echo $helperService->getFormattedPrice($appData['invoice_unit_price'] * $appData['invoice_qty']) ?></td>
                    <?php if ($invoiceData['taxEnabled']) : ?>
                        <td><?php echo !empty($appData['invoice_tax_rate']) ? $appData['invoice_tax_rate'] : 0 ?></td>
                        <td><?php echo $helperService->getFormattedPrice(!empty($appData['invoice_tax']) ? $appData['invoice_tax'] : 0) ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </table>

        <div class="invoice-body-sum">
            <?php if ($invoiceData['subtotal'] !== $invoiceData['total']) : ?>
                <div>
                    <span><?php echo BackendStrings::get('invoice_subtotal');?></span>
                    <span><?php echo $helperService->getFormattedPrice($invoiceData['subtotal']) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($invoiceData['discount'] > 0) : ?>
                <div>
                    <span><?php echo BackendStrings::get('invoice_discount'); ?></span>
                    <span><?php echo $helperService->getFormattedPrice($invoiceData['discount']) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($invoiceData['tax'] > 0) : ?>
                <div>
                    <span><?php echo BackendStrings::get('invoice_tax'); ?></span>
                    <span><?php echo $helperService->getFormattedPrice($invoiceData['tax']) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($invoiceData['subtotal'] !== $invoiceData['total'] || $invoiceData['tax'] > 0) : ?>
                <hr>
            <?php endif; ?>
            <div class="invoice-body-total">
                <span><?php echo BackendStrings::get('invoice_total') .
                        ($invoiceData['includedTax'] && $invoiceData['total'] > 0 ? BackendStrings::get('invoice_incl_tax') : ''); ?></span>
                <span><?php echo $helperService->getFormattedPrice($invoiceData['total']) ?></span>
            </div>
            <?php if ($invoiceData['paid']) : ?>
                <hr>
                <div>
                    <span><?php echo BackendStrings::get('invoice_paid'); ?></span>
                    <span><?php echo $helperService->getFormattedPrice($invoiceData['paid']) ?></span>
                </div>
                <div>
                    <span><?php echo BackendStrings::get('invoice_left_to_pay'); ?></span>
                    <span><?php echo $helperService->getFormattedPrice($invoiceData['leftToPay']) ?></span>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <div class="invoice-footer">
<!--        <div class="invoice-footer-info">--><?php //echo BackendStrings::get('invoice_payment_info'); ?><!--</div>-->
        <div class="invoice-footer-method">
            <div><?php echo BackendStrings::get('invoice_payment_method'); ?></div>
            <div><?php echo $data['invoice_method'] ?></div>
        </div>
    </div>

</div>

</body>
</html>
