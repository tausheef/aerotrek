<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #111; background: #fff; }

  .page { padding: 18px 20px; }

  /* ── Header ── */
  .header-table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
  .header-table td { padding: 4px 6px; vertical-align: middle; }
  .title-cell { text-align: center; font-size: 14px; font-weight: bold; letter-spacing: 1px; }

  /* ── Main border box ── */
  .main-box { border: 1px solid #555; width: 100%; }

  /* ── Top info grid ── */
  .top-grid { width: 100%; border-collapse: collapse; }
  .top-grid td { border: 1px solid #555; padding: 5px 6px; vertical-align: top; }

  .section-label { font-weight: bold; font-size: 8.5px; margin-bottom: 3px; border-bottom: 1px solid #ccc; padding-bottom: 2px; }
  .val { font-size: 8.5px; line-height: 1.5; }
  .val b { font-weight: bold; }

  /* ── Meta grid (right side) ── */
  .meta-table { width: 100%; border-collapse: collapse; }
  .meta-table td { padding: 2px 4px; font-size: 8.5px; vertical-align: top; }
  .meta-label { font-weight: bold; white-space: nowrap; width: 48%; }
  .meta-val { }

  /* ── Products table ── */
  .products-table { width: 100%; border-collapse: collapse; margin-top: 0; }
  .products-table th {
    background: #f0f0f0;
    border: 1px solid #555;
    padding: 4px 5px;
    font-size: 8px;
    font-weight: bold;
    text-align: center;
  }
  .products-table td {
    border: 1px solid #555;
    padding: 3px 5px;
    font-size: 8.5px;
    text-align: center;
    vertical-align: middle;
  }
  .products-table td.left { text-align: left; }
  .total-row td { font-weight: bold; background: #f8f8f8; }

  /* ── Declaration + Signature ── */
  .bottom-grid { width: 100%; border-collapse: collapse; }
  .bottom-grid td { border: 1px solid #555; padding: 8px 10px; vertical-align: top; }

  .declaration-text { font-size: 8.5px; line-height: 1.6; }
  .signature-block { text-align: center; }
  .signature-block img { max-height: 55px; max-width: 140px; }
  .signature-label { font-size: 8px; font-weight: bold; margin-top: 4px; border-top: 1px solid #999; padding-top: 3px; }

  /* ── Footer ── */
  .footer { text-align: center; font-size: 8px; font-style: italic; color: #444; margin-top: 6px; }

  /* ── AeroTrek brand bar ── */
  .brand-bar { background: #1e3a5f; color: #fff; text-align: center; padding: 5px 0; font-size: 11px; font-weight: bold; letter-spacing: 2px; margin-bottom: 4px; }
</style>
</head>
<body>
<div class="page">

  {{-- Brand bar --}}
  <div class="brand-bar">AEROTREK COURIER</div>

  {{-- Title --}}
  <table class="header-table">
    <tr><td class="title-cell">EXPORT INVOICE</td></tr>
  </table>

  <div class="main-box">

    {{-- Row 1: Addresses + Meta --}}
    <table class="top-grid">
      <tr>
        {{-- Left col: Shipping Address + Consignee + Billing --}}
        <td style="width:38%; border-right:1px solid #555;">

          <div class="section-label">Shipping Address</div>
          <div class="val">
            <b>{{ $sender['name'] ?? '' }}</b><br>
            {{ $sender['address_line1'] ?? '' }}
            @if(!empty($sender['address_line2']))
              {{ $sender['address_line2'] }}
            @endif
            <br>
            {{ $sender['city'] ?? '' }}, {{ $sender['state'] ?? '' }} {{ $sender['pincode'] ?? '' }}<br>
            India<br>
            Ph: {{ $sender['phone'] ?? '' }}<br>
            Email: {{ $shipment->user->email ?? '' }}
          </div>

          <br>
          <div class="section-label">Consignee</div>
          <div class="val">
            <b>{{ $receiver['name'] ?? '' }}</b><br>
            {{ $receiver['address_line1'] ?? '' }}
            @if(!empty($receiver['address_line2']))
              {{ $receiver['address_line2'] }}
            @endif
            <br>
            {{ $receiver['city'] ?? '' }}
            @if(!empty($receiver['state']))
              {{ $receiver['state'] }}
            @endif
            {{ $receiver['zipcode'] ?? '' }}<br>
            {{ $receiver['country_code'] ?? '' }}<br>
            Ph: {{ $receiver['phone'] ?? '' }}
          </div>

          <br>
          <div class="section-label">Billing Address</div>
          <div class="val">
            <b>{{ $receiver['name'] ?? '' }}</b><br>
            {{ $receiver['address_line1'] ?? '' }}<br>
            {{ $receiver['city'] ?? '' }} {{ $receiver['zipcode'] ?? '' }}<br>
            @if(!empty($receiver['state']))
              {{ $receiver['state'] }}<br>
            @endif
            {{ $receiver['country_code'] ?? '' }}<br>
            Ph: {{ $receiver['phone'] ?? '' }}
            @if(!empty($receiver['email']))
              <br>Email: {{ $receiver['email'] }}
            @endif
          </div>

        </td>

        {{-- Middle col: Origin/Destination --}}
        <td style="width:22%; border-right:1px solid #555;">

          <div class="section-label">Country of Origin of Goods:</div>
          <div class="val">India</div>
          <br>
          <div class="section-label">Country of Final Destination:</div>
          <div class="val">{{ $receiver['country_code'] ?? '' }}</div>
          <br>
          <div class="section-label">State and City of Supply:</div>
          <div class="val">{{ strtoupper($sender['state'] ?? '') }}, {{ strtoupper($sender['city'] ?? '') }}</div>
          <br>
          <div class="section-label">Final Destination:</div>
          <div class="val">{{ $receiver['country_code'] ?? '' }}</div>

        </td>

        {{-- Right col: Invoice meta --}}
        <td style="width:40%;">

          <table class="meta-table">
            <tr>
              <td class="meta-label">GST Invoice Number</td>
              <td class="meta-val">{{ $shipment->invoice_no ?? '—' }}</td>
            </tr>
            <tr>
              <td class="meta-label">Invoice Date</td>
              <td class="meta-val">
                @php
                  try {
                    echo \Carbon\Carbon::parse($shipment->invoice_date)->format('D, M j, Y g:i A');
                  } catch(\Exception $e) {
                    echo $shipment->invoice_date ?? '—';
                  }
                @endphp
              </td>
            </tr>
            <tr>
              <td class="meta-label">AeroTrek ID</td>
              <td class="meta-val"><b>{{ $shipment->aerotrek_id }}</b></td>
            </tr>
            <tr>
              <td class="meta-label">AWB Number</td>
              <td class="meta-val">{{ $shipment->awb_no ?? '—' }}</td>
            </tr>
            <tr>
              <td colspan="2" style="padding-top:4px;"></td>
            </tr>
            @if($gstin)
            <tr>
              <td class="meta-label">GSTIN</td>
              <td class="meta-val">{{ $gstin }}</td>
            </tr>
            @endif
            @if($iecCode)
            <tr>
              <td class="meta-label">IEC Code</td>
              <td class="meta-val">{{ $iecCode }}</td>
            </tr>
            @endif
            <tr>
              <td class="meta-label">Incoterms</td>
              <td class="meta-val">{{ $shipment->terms_of_sale ?? '—' }}</td>
            </tr>
            <tr>
              <td class="meta-label">CSB Type</td>
              <td class="meta-val">{{ $shipment->csb_type ?? '—' }}</td>
            </tr>
            <tr>
              <td class="meta-label">Duty &amp; Tax</td>
              <td class="meta-val">{{ $shipment->duty_tax ?? '—' }}</td>
            </tr>
            <tr>
              <td class="meta-label">Purpose of Shipment</td>
              <td class="meta-val">{{ $shipment->reason_for_export ?? '—' }}</td>
            </tr>
            <tr>
              <td class="meta-label">Carrier</td>
              <td class="meta-val">{{ $shipment->carrier ?? '—' }}</td>
            </tr>
            <tr>
              <td class="meta-label">Service</td>
              <td class="meta-val">{{ $shipment->service_name ?? '—' }}</td>
            </tr>
            <tr>
              <td class="meta-label">Port of Loading</td>
              <td class="meta-val">India</td>
            </tr>
            <tr>
              <td class="meta-label">Invoice Currency</td>
              <td class="meta-val">{{ $shipment->invoice_currency ?? 'INR' }}</td>
            </tr>
          </table>

        </td>
      </tr>
    </table>

    {{-- Products table --}}
    @php
      $currency = $shipment->invoice_currency ?? 'INR';
      $products = $shipment->products ?? [];
      $totalQty   = 0;
      $totalValue = 0;
      foreach ($products as $p) {
        $totalQty   += (int)($p['qty'] ?? 0);
        $totalValue += (float)($p['total_value'] ?? ((($p['qty'] ?? 0) * ($p['unit_rate'] ?? 0))));
      }
    @endphp

    @if(count($products) > 0)
    <table class="products-table">
      <thead>
        <tr>
          <th style="text-align:left; width:28%">Description of Goods</th>
          <th style="width:9%">HSN</th>
          <th style="width:9%">HTS</th>
          <th style="width:9%">Qty (pcs.)</th>
          <th style="width:11%">Rate ({{ $currency }})</th>
          <th style="width:14%">Total Taxable Value ({{ $currency }})</th>
          <th style="width:9%">IGST ({{ $currency }})</th>
          <th style="width:11%">Total ({{ $currency }})</th>
        </tr>
      </thead>
      <tbody>
        @foreach($products as $product)
          @php
            $qty        = (int)($product['qty'] ?? 0);
            $rate       = (float)($product['unit_rate'] ?? 0);
            $totalVal   = (float)($product['total_value'] ?? ($qty * $rate));
            $igst       = 0; // export — zero rated
            $lineTotal  = $totalVal + $igst;
          @endphp
          <tr>
            <td class="left">{{ $product['description'] ?? '' }}</td>
            <td>{{ $product['hsn_code'] ?? '' }}</td>
            <td>{{ $product['hts_code'] ?? '' }}</td>
            <td>{{ $qty }}</td>
            <td>{{ number_format($rate, 2) }}</td>
            <td>{{ number_format($totalVal, 2) }}</td>
            <td>{{ number_format($igst, 2) }}</td>
            <td>{{ number_format($lineTotal, 2) }}</td>
          </tr>
        @endforeach
        <tr class="total-row">
          <td class="left" colspan="3">Total:</td>
          <td>{{ $totalQty }}</td>
          <td></td>
          <td>{{ number_format($totalValue, 2) }}</td>
          <td>0.00</td>
          <td>{{ number_format($totalValue, 2) }}</td>
        </tr>
      </tbody>
    </table>
    @endif

    {{-- Declaration + Signature --}}
    <table class="bottom-grid">
      <tr>
        <td style="width:55%;">
          <div class="declaration-text">
            <b>Declaration</b><br><br>
            We declare that this invoice shows the actual price of the goods<br>
            described and that all particulars are true and correct.
          </div>
        </td>
        <td style="width:45%;">
          <div class="signature-block">
            @if($signatureBase64)
              <img src="data:image/png;base64,{{ $signatureBase64 }}" alt="Signature">
            @else
              <div style="height:55px;"></div>
            @endif
            <div class="signature-label">Signature &amp; Date</div>
          </div>
        </td>
      </tr>
    </table>

  </div>{{-- .main-box --}}

  <div class="footer">
    THIS IS AN AUTO-GENERATED INVOICE AND DOES NOT NEED SIGNATURE.
  </div>

</div>
</body>
</html>
