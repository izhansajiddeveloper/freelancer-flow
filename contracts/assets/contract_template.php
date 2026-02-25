<?php
/**
 * Contract HTML Template for PDF Generation
 * Expects $c (contract data) to be available
 */

$date = date('d F Y', strtotime($c['created_at']));
$contract_no = "FF-CON-" . str_pad($c['id'], 3, '0', STR_PAD_LEFT);
$prop_no     = "FF-PR-" . str_pad($c['proposal_id'], 3, '0', STR_PAD_LEFT);

return '
<style>
    .page-container { font-family: "Helvetica", sans-serif; padding: 10px; }
    .title-banner { text-align: center; margin-bottom: 40px; padding: 10px 0; }
    .title-banner h1 { font-size: 22pt; margin: 0; letter-spacing: 3px; font-weight: bold; color: #1e293b; }
    
    .meta-grid { width: 100%; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 30px; }
    .meta-label { font-size: 9pt; font-weight: bold; color: #64748b; text-transform: uppercase; margin-bottom: 2px; }
    .meta-value { font-size: 10pt; color: #1e293b; margin-bottom: 15px; line-height: 1.4; }

    .section-title-bar { padding: 8px 0; margin-top: 20px; margin-bottom: 15px; }
    .section-title { font-size: 13pt; font-weight: bold; color: #1e293b; text-decoration: none; }
    
    .content-text { font-size: 10.5pt; line-height: 1.7; color: #334155; }
    
    .signature-grid { margin-top: 60px; }
    .sig-line { border-bottom: 1px solid #94a3b8; height: 50px; width: 220px; }
    .sig-label { font-size: 9pt; font-weight: bold; color: #475569; margin-top: 10px; }

    .footer-divider { text-align: center; margin-top: 60px; padding-top: 20px; border-top: 1px solid #f1f5f9; }
    .footer-text { font-size: 10pt; font-style: italic; color: #64748b; line-height: 1.5; }
</style>

<div class="page-container">
    <div class="title-banner">
        <h1>FREELANCE SERVICE AGREEMENT</h1>
    </div>

    <table width="100%" class="meta-grid">
        <tr>
            <td width="45%">
                <div class="meta-label">Prepared By:</div>
                <div class="meta-value">
                    <strong>'.htmlspecialchars($c['user_name']).'</strong><br>
                    '.($c['job_title'] ? htmlspecialchars($c['job_title']).'<br>' : '').'
                    Email: '.htmlspecialchars($c['user_email']).'
                    '.($c['user_phone'] ? '<br>Phone: '.htmlspecialchars($c['user_phone']) : '').'
                </div>
            </td>
            <td width="10%"></td>
            <td width="45%">
                <div class="meta-label">Prepared For:</div>
                <div class="meta-value">
                    <strong>'.htmlspecialchars($c['client_name']).'</strong><br>
                    '.($c['client_company'] ? htmlspecialchars($c['client_company']).'<br>' : '').'
                    Email: '.htmlspecialchars($c['client_email']).'
                </div>
            </td>
        </tr>
    </table>

    <table width="100%" style="margin-bottom: 30px;">
        <tr>
            <td width="33%">
                <div class="meta-label">Contract Date:</div>
                <div class="meta-value">'.$date.'</div>
            </td>
            <td width="33%">
                <div class="meta-label">Contract Number:</div>
                <div class="meta-value">'.$contract_no.'</div>
            </td>
            <td width="33%">
                <div class="meta-label">Related Proposal:</div>
                <div class="meta-value">'.$prop_no.'</div>
            </td>
        </tr>
    </table>

    <div class="content-text">
        '.preg_replace_callback("/^### (.*)$/m", function($matches) {
            return '<div class="section-title-bar"><span class="section-title">'.$matches[1].'</span></div>';
        }, nl2br(htmlspecialchars($c['contract_details']))).'
    </div>

    <div class="signature-grid">
        <table width="100%">
            <tr>
                <td width="48%">
                    <div class="sig-line"></div>
                    <div class="sig-label">Freelancer Signature</div>
                    <div style="font-size: 9pt; margin-top: 20px; color: #64748b;">Date: __________________________</div>
                </td>
                <td width="4%"></td>
                <td width="48%">
                    <div class="sig-line"></div>
                    <div class="sig-label">Client Signature</div>
                    <div style="font-size: 9pt; margin-top: 10px; color: #64748b;">Name: '.htmlspecialchars($c['client_name']).'</div>
                    <div style="font-size: 9pt; margin-top: 10px; color: #64748b;">Date: __________________________</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer-divider">
        <div class="footer-text">
            Thank you for your trust and collaboration.<br>
            We look forward to a successful project completion.
        </div>
    </div>
</div>
';
