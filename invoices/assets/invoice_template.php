<?php
// HTML Template for Invoice PDF
// Expects $inv array to be available

$primary = '#0B428A';
$light_bg = '#f8fafc';
$border = '#e2e8f0';
$text = '#334155';

$tax_amt = $inv['subtotal'] * ($inv['tax'] / 100);

$html = '
<style>
    body { font-family: helvetica; color: ' . $text . '; font-size: 10pt; line-height: 1.4; }
    .label { color: #64748b; font-size: 9pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
    .val { color: #1e293b; font-size: 10pt; }
    .section-head { font-size: 12pt; font-weight: bold; color: #1e293b; }
    .th { background-color: ' . $primary . '; color: #ffffff; font-weight: bold; font-size: 9pt; }
    .td-cell { color: ' . $text . '; font-size: 9pt; border-bottom: 1px solid ' . $border . '; }
    .total-bar { background-color: ' . $primary . '; color: #ffffff; font-weight: bold; font-size: 11pt; }
    .box { background-color: ' . $light_bg . '; border: 1px solid ' . $border . '; padding: 8px; }
    .thank { font-size: 22pt; color: ' . $primary . '; font-weight: bold; }
    .status-badge { font-weight: bold; font-size: 9pt; color: #ffffff; background-color: #f59e0b; padding: 3px 10px; border-radius: 20px; }
</style>

<!-- Bill To / From -->
<table width="100%" cellpadding="5" cellspacing="0">
    <tr>
        <td width="50%">
            <span class="section-head">Bill To:</span><br><br>
            <span class="val">' . htmlspecialchars($inv['client_name']) . '</span><br>
            ' . (!empty($inv['client_company']) ? '<span class="val">' . htmlspecialchars($inv['client_company']) . '</span><br>' : '') . '
            ' . (!empty($inv['client_phone']) ? '<span class="val">' . htmlspecialchars($inv['client_phone']) . '</span><br>' : '') . '
            <span class="val">' . htmlspecialchars($inv['client_email']) . '</span>
        </td>
        <td width="50%" align="right">
            <span class="section-head">From:</span><br><br>
            <span class="val">' . htmlspecialchars($inv['user_name']) . '</span><br>
            ' . (!empty($inv['user_phone']) ? '<span class="val">' . htmlspecialchars($inv['user_phone']) . '</span><br>' : '') . '
            <span class="val">' . htmlspecialchars($inv['user_email']) . '</span>
        </td>
    </tr>
</table>

<br>

<!-- Date & Status -->
<table width="100%" cellpadding="4" cellspacing="0">
    <tr>
        <td>
            <span class="label">Issue Date:</span> <span class="val">' . date('d F Y', strtotime($inv['issue_date'])) . '</span>
            &nbsp;&nbsp;&nbsp;
            <span class="label">Due Date:</span> <span class="val">' . date('d F Y', strtotime($inv['due_date'])) . '</span>
        </td>
        <td align="right">
            <span class="label">Status:</span> <span class="val">' . strtoupper($inv['status']) . '</span>
        </td>
    </tr>
</table>

<br>

<!-- Items Table -->
<table width="100%" cellpadding="7" cellspacing="0" border="0">
    <tr>
        <td width="48%" class="th" align="left">Description</td>
        <td width="14%" class="th" align="center">Qty</td>
        <td width="19%" class="th" align="center">Price</td>
        <td width="19%" class="th" align="right">Total</td>
    </tr>
    <tr>
        <td width="48%" class="td-cell" align="left">' . (!empty($inv['project_title']) ? htmlspecialchars($inv['project_title']) : 'Professional Services') . '</td>
        <td width="14%" class="td-cell" align="center">1</td>
        <td width="19%" class="td-cell" align="center">RS ' . number_format($inv['subtotal'], 2) . '</td>
        <td width="19%" class="td-cell" align="right">RS ' . number_format($inv['subtotal'], 2) . '</td>
    </tr>';

if ($inv['tax'] > 0) {
    $html .= '
    <tr>
        <td width="48%" class="td-cell" align="left">Tax (' . number_format($inv['tax'], 2) . '%)</td>
        <td width="14%" class="td-cell" align="center">-</td>
        <td width="19%" class="td-cell" align="center">RS ' . number_format($tax_amt, 2) . '</td>
        <td width="19%" class="td-cell" align="right">RS ' . number_format($tax_amt, 2) . '</td>
    </tr>';
}

$html .= '
</table>

<!-- Sub Total bar -->
<table width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td width="62%"></td>
        <td width="38%">
            <table width="100%" cellpadding="7" cellspacing="0" class="total-bar">
                <tr>
                    <td align="left">TOTAL DUE</td>
                    <td align="right">RS ' . number_format($inv['total_amount'], 2) . '</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<br>';

if (!empty($inv['notes'])) {
    $html .= '
<table width="100%" cellpadding="6" cellspacing="0">
    <tr>
        <td class="box">
            <span class="label">Notes:</span><br>
            <span class="val">' . nl2br(htmlspecialchars($inv['notes'])) . '</span>
        </td>
    </tr>
</table>
<br>';
}

$paid_line = ($inv['status'] === 'paid' && !empty($inv['paid_date'])) ? 'Paid on: ' . date('d F Y', strtotime($inv['paid_date'])) : 'Payment pending';

$html .= '
<table width="100%" cellpadding="5" cellspacing="0">
    <tr>
        <td width="55%">
            <span class="label">Payment Information:</span><br><br>
            <table width="100%" cellpadding="2">
                <tr>
                    <td width="28%" class="label">Bank:</td>
                    <td class="val">Virtual Bank</td>
                </tr>
                <tr>
                    <td width="28%" class="label">Email:</td>
                    <td class="val">' . htmlspecialchars($inv['user_email']) . '</td>
                </tr>
                <tr>
                    <td width="28%" class="label">Status:</td>
                    <td class="val">' . $paid_line . '</td>
                </tr>
            </table>
        </td>
        <td width="45%" align="center" valign="middle">
            <span class="thank">Thank You!</span>
        </td>
    </tr>
</table>

<br>
<table width="100%" cellpadding="3" cellspacing="0">
    <tr>
        <td align="center" style="color: #94a3b8; font-size: 8pt; border-top: 1px solid #e2e8f0; padding-top: 8px;">
            This is a system-generated invoice from FreelanceFlow &bull; ' . date('d F Y') . '
        </td>
    </tr>
</table>
';

return $html;
