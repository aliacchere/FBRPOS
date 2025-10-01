<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptService
{
    /**
     * Generate receipt PDF
     */
    public function generateReceipt(Sale $sale)
    {
        $data = [
            'sale' => $sale,
            'tenant' => $sale->tenant,
            'items' => $sale->items,
            'qr_code_data' => $this->generateQrCodeData($sale)
        ];

        $pdf = Pdf::loadView('receipts.template', $data);
        
        return $pdf->stream('receipt-' . $sale->invoice_number . '.pdf');
    }

    /**
     * Generate receipt HTML for display
     */
    public function generateReceiptHtml(Sale $sale)
    {
        $data = [
            'sale' => $sale,
            'tenant' => $sale->tenant,
            'items' => $sale->items,
            'qr_code_data' => $this->generateQrCodeData($sale)
        ];

        return view('receipts.display', $data);
    }

    /**
     * Generate QR code data
     */
    private function generateQrCodeData(Sale $sale): array
    {
        return [
            'invoice' => $sale->invoice_number,
            'date' => $sale->sale_date,
            'total' => $sale->total_amount,
            'fbr' => $sale->fbr_invoice_number ?? '',
            'business' => $sale->tenant->business_name,
            'verification_url' => config('app.url') . '/receipt/' . $sale->id
        ];
    }

    /**
     * Generate receipt template based on tenant settings
     */
    public function getReceiptTemplate($tenantId, $type = 'receipt')
    {
        $template = \App\Models\Template::where('tenant_id', $tenantId)
            ->where('type', $type)
            ->where('is_default', true)
            ->first();

        if (!$template) {
            return $this->getDefaultTemplate($type);
        }

        return $template->content;
    }

    /**
     * Get default receipt template
     */
    private function getDefaultTemplate($type)
    {
        $templates = [
            'receipt' => $this->getDefaultReceiptTemplate(),
            'invoice' => $this->getDefaultInvoiceTemplate(),
            'purchase_order' => $this->getDefaultPurchaseOrderTemplate(),
            'quotation' => $this->getDefaultQuotationTemplate()
        ];

        return $templates[$type] ?? $templates['receipt'];
    }

    /**
     * Default receipt template
     */
    private function getDefaultReceiptTemplate(): string
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Receipt - {{invoice_number}}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                .header { text-align: center; margin-bottom: 20px; }
                .business-name { font-size: 24px; font-weight: bold; color: #333; }
                .receipt-title { font-size: 18px; color: #666; margin-top: 10px; }
                .receipt-info { display: flex; justify-content: space-between; margin-bottom: 20px; }
                .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .items-table th { background-color: #f2f2f2; }
                .total-section { text-align: right; margin-top: 20px; }
                .total-row { margin: 5px 0; }
                .grand-total { font-size: 18px; font-weight: bold; border-top: 2px solid #333; padding-top: 10px; }
                .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
                .qr-code { text-align: center; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="business-name">{{business_name}}</div>
                <div class="receipt-title">Receipt</div>
            </div>
            
            <div class="receipt-info">
                <div>
                    <strong>Invoice #:</strong> {{invoice_number}}<br>
                    <strong>Date:</strong> {{sale_date}}<br>
                    <strong>Cashier:</strong> {{cashier_name}}
                </div>
                <div>
                    <strong>Payment:</strong> {{payment_method}}<br>
                    @if(fbr_invoice_number)
                    <strong>FBR Invoice #:</strong> {{fbr_invoice_number}}<br>
                    <strong>Status:</strong> FBR Verified
                    @endif
                </div>
            </div>
            
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(items as item)
                    <tr>
                        <td>{{item.product.name}}</td>
                        <td>{{item.quantity}}</td>
                        <td>Rs. {{number_format(item.unit_price, 2)}}</td>
                        <td>Rs. {{number_format(item.total_price, 2)}}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            
            <div class="total-section">
                <div class="total-row"><strong>Subtotal:</strong> Rs. {{number_format(subtotal, 2)}}</div>
                <div class="total-row"><strong>Tax (18%):</strong> Rs. {{number_format(tax_amount, 2)}}</div>
                <div class="total-row grand-total"><strong>Total:</strong> Rs. {{number_format(total_amount, 2)}}</div>
            </div>
            
            <div class="qr-code">
                <div>QR Code: {{qr_code_data}}</div>
            </div>
            
            <div class="footer">
                <p>Thank you for your business!</p>
                <p>This receipt was generated by DPS POS FBR Integrated</p>
                <p>Generated on: {{now()}}</p>
            </div>
        </body>
        </html>';
    }

    /**
     * Default invoice template
     */
    private function getDefaultInvoiceTemplate(): string
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Invoice - {{invoice_number}}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
                .business-info { flex: 1; }
                .invoice-info { flex: 1; text-align: right; }
                .business-name { font-size: 28px; font-weight: bold; color: #333; }
                .invoice-title { font-size: 32px; font-weight: bold; color: #666; }
                .invoice-number { font-size: 18px; color: #333; }
                .items-table { width: 100%; border-collapse: collapse; margin: 30px 0; }
                .items-table th, .items-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                .items-table th { background-color: #f8f9fa; font-weight: bold; }
                .total-section { text-align: right; margin-top: 30px; }
                .total-row { margin: 8px 0; font-size: 16px; }
                .grand-total { font-size: 20px; font-weight: bold; border-top: 3px solid #333; padding-top: 15px; }
                .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="business-info">
                    <div class="business-name">{{business_name}}</div>
                    <div>{{address}}</div>
                    <div>{{city}}, {{province}}</div>
                    <div>Phone: {{phone}}</div>
                    <div>Email: {{email}}</div>
                    <div>NTN: {{ntn}}</div>
                </div>
                <div class="invoice-info">
                    <div class="invoice-title">INVOICE</div>
                    <div class="invoice-number"># {{invoice_number}}</div>
                    <div><strong>Date:</strong> {{sale_date}}</div>
                    <div><strong>Due Date:</strong> {{sale_date}}</div>
                </div>
            </div>
            
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Qty</th>
                        <th>Rate</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(items as item)
                    <tr>
                        <td>{{item.product.name}}</td>
                        <td>{{item.quantity}}</td>
                        <td>Rs. {{number_format(item.unit_price, 2)}}</td>
                        <td>Rs. {{number_format(item.total_price, 2)}}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            
            <div class="total-section">
                <div class="total-row"><strong>Subtotal:</strong> Rs. {{number_format(subtotal, 2)}}</div>
                <div class="total-row"><strong>Tax (18%):</strong> Rs. {{number_format(tax_amount, 2)}}</div>
                <div class="total-row grand-total"><strong>Total Amount:</strong> Rs. {{number_format(total_amount, 2)}}</div>
            </div>
            
            <div class="footer">
                <p>Thank you for your business!</p>
                <p>This invoice was generated by DPS POS FBR Integrated</p>
            </div>
        </body>
        </html>';
    }

    /**
     * Default purchase order template
     */
    private function getDefaultPurchaseOrderTemplate(): string
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Purchase Order - {{po_number}}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
                .business-info { flex: 1; }
                .po-info { flex: 1; text-align: right; }
                .business-name { font-size: 28px; font-weight: bold; color: #333; }
                .po-title { font-size: 32px; font-weight: bold; color: #666; }
                .po-number { font-size: 18px; color: #333; }
                .items-table { width: 100%; border-collapse: collapse; margin: 30px 0; }
                .items-table th, .items-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                .items-table th { background-color: #f8f9fa; font-weight: bold; }
                .total-section { text-align: right; margin-top: 30px; }
                .total-row { margin: 8px 0; font-size: 16px; }
                .grand-total { font-size: 20px; font-weight: bold; border-top: 3px solid #333; padding-top: 15px; }
                .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="business-info">
                    <div class="business-name">{{business_name}}</div>
                    <div>{{address}}</div>
                    <div>{{city}}, {{province}}</div>
                    <div>Phone: {{phone}}</div>
                    <div>Email: {{email}}</div>
                </div>
                <div class="po-info">
                    <div class="po-title">PURCHASE ORDER</div>
                    <div class="po-number"># {{po_number}}</div>
                    <div><strong>Date:</strong> {{created_at}}</div>
                    <div><strong>Expected Date:</strong> {{expected_date}}</div>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <strong>Supplier:</strong> {{supplier.name}}<br>
                <strong>Address:</strong> {{supplier.address}}<br>
                <strong>Phone:</strong> {{supplier.phone}}<br>
                <strong>Email:</strong> {{supplier.email}}
            </div>
            
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Qty</th>
                        <th>Rate</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(items as item)
                    <tr>
                        <td>{{item.product.name}}</td>
                        <td>{{item.quantity}}</td>
                        <td>Rs. {{number_format(item.unit_price, 2)}}</td>
                        <td>Rs. {{number_format(item.total_price, 2)}}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            
            <div class="total-section">
                <div class="total-row"><strong>Subtotal:</strong> Rs. {{number_format(subtotal, 2)}}</div>
                <div class="total-row"><strong>Tax (18%):</strong> Rs. {{number_format(tax_amount, 2)}}</div>
                <div class="total-row grand-total"><strong>Total Amount:</strong> Rs. {{number_format(total_amount, 2)}}</div>
            </div>
            
            <div class="footer">
                <p>Please deliver the above items as per the specifications.</p>
                <p>This purchase order was generated by DPS POS FBR Integrated</p>
            </div>
        </body>
        </html>';
    }

    /**
     * Default quotation template
     */
    private function getDefaultQuotationTemplate(): string
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Quotation - {{quotation_number}}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
                .business-info { flex: 1; }
                .quotation-info { flex: 1; text-align: right; }
                .business-name { font-size: 28px; font-weight: bold; color: #333; }
                .quotation-title { font-size: 32px; font-weight: bold; color: #666; }
                .quotation-number { font-size: 18px; color: #333; }
                .items-table { width: 100%; border-collapse: collapse; margin: 30px 0; }
                .items-table th, .items-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                .items-table th { background-color: #f8f9fa; font-weight: bold; }
                .total-section { text-align: right; margin-top: 30px; }
                .total-row { margin: 8px 0; font-size: 16px; }
                .grand-total { font-size: 20px; font-weight: bold; border-top: 3px solid #333; padding-top: 15px; }
                .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #666; }
                .validity { margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #007bff; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="business-info">
                    <div class="business-name">{{business_name}}</div>
                    <div>{{address}}</div>
                    <div>{{city}}, {{province}}</div>
                    <div>Phone: {{phone}}</div>
                    <div>Email: {{email}}</div>
                    <div>NTN: {{ntn}}</div>
                </div>
                <div class="quotation-info">
                    <div class="quotation-title">QUOTATION</div>
                    <div class="quotation-number"># {{quotation_number}}</div>
                    <div><strong>Date:</strong> {{created_at}}</div>
                    <div><strong>Valid Until:</strong> {{valid_until}}</div>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <strong>Customer:</strong> {{customer.name}}<br>
                <strong>Address:</strong> {{customer.address}}<br>
                <strong>Phone:</strong> {{customer.phone}}<br>
                <strong>Email:</strong> {{customer.email}}
            </div>
            
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Qty</th>
                        <th>Rate</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(items as item)
                    <tr>
                        <td>{{item.product.name}}</td>
                        <td>{{item.quantity}}</td>
                        <td>Rs. {{number_format(item.unit_price, 2)}}</td>
                        <td>Rs. {{number_format(item.total_price, 2)}}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            
            <div class="total-section">
                <div class="total-row"><strong>Subtotal:</strong> Rs. {{number_format(subtotal, 2)}}</div>
                <div class="total-row"><strong>Tax (18%):</strong> Rs. {{number_format(tax_amount, 2)}}</div>
                <div class="total-row grand-total"><strong>Total Amount:</strong> Rs. {{number_format(total_amount, 2)}}</div>
            </div>
            
            <div class="validity">
                <strong>Validity:</strong> This quotation is valid for 30 days from the date of issue.
            </div>
            
            <div class="footer">
                <p>Thank you for considering our services!</p>
                <p>This quotation was generated by DPS POS FBR Integrated</p>
            </div>
        </body>
        </html>';
    }
}