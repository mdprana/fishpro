<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Analisa {{ $po->po_number }}</title>
    <style>
        body{font-family:'Segoe UI',Arial,sans-serif;font-size:11px;color:#0f172a;margin:16px;}
        h1{font-size:16px;margin:0 0 2px;}
        h2{font-size:12px;margin:14px 0 5px;border-bottom:1.5px solid #1a3a5c;padding-bottom:3px;color:#1a3a5c;}
        table{width:100%;border-collapse:collapse;margin-bottom:8px;}
        th,td{border:1px solid #cbd5e1;padding:3px 5px;text-align:left;}
        th{background:#f1f5f9;font-size:10px;}
        td.num,th.num{text-align:right;}
        td.ctr,th.ctr{text-align:center;}
        .meta{color:#64748b;font-size:10px;margin-bottom:10px;}
        .kpi{display:flex;gap:8px;margin-bottom:12px;}
        .kpi div{flex:1;border:1px solid #cbd5e1;border-radius:4px;padding:6px;}
        .kpi span{display:block;color:#64748b;font-size:9px;}
        .kpi strong{font-size:14px;}
        .bad{background:#fee2e2;}
        .no-print{margin-bottom:12px;}
        @media print{ .no-print{display:none;} body{margin:0;} @page{margin:10mm;} }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()">Cetak</button>
    <a href="{{ route('production.analisa', ['po_id' => $po->id]) }}">Kembali</a>
</div>

<h1>Analisa Produksi {{ $po->po_number }}</h1>
<div class="meta">
    Buyer: {{ $po->buyer_name }} ·
    Order {{ $po->order_date->format('d/m/Y') }} - Deadline {{ $po->deadline->format('d/m/Y') }} ·
    Dicetak {{ now()->format('d/m/Y H:i') }}
</div>

@php $r = $analisa['recovery']; @endphp
<div class="kpi">
    <div><span>Bahan Baku</span><strong>{{ number_format($r['bahan_kg'], 2) }} kg</strong></div>
    <div><span>Hasil Packing</span><strong>{{ number_format($r['packing_kg'], 2) }} kg</strong></div>
    <div><span>Susut</span><strong>{{ number_format($r['susut_kg'], 2) }} kg</strong></div>
    <div><span>Recovery</span><strong>{{ number_format($r['recovery_pct'], 1) }}%</strong></div>
</div>

<h2>1. Rekap Bahan Baku per Supplier</h2>
<table>
    <thead><tr><th>Kode</th><th>Supplier</th><th class="ctr">Batch</th>
        <th class="num">Masuk (kg)</th><th class="num">Terpakai</th><th class="num">Sisa</th></tr></thead>
    <tbody>
        @forelse($analisa['bahanPerSupplier'] as $row)
        <tr>
            <td>{{ $row['code'] }}</td><td>{{ $row['name'] }}</td><td class="ctr">{{ $row['batch'] }}</td>
            <td class="num">{{ number_format($row['masuk_kg'], 2) }}</td>
            <td class="num">{{ number_format($row['terpakai_kg'], 2) }}</td>
            <td class="num">{{ number_format($row['sisa_kg'], 2) }}</td>
        </tr>
        @empty
        <tr><td colspan="6">Belum ada penerimaan.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>2. Rekap Bahan Baku per Batch</h2>
<table>
    <thead><tr><th>Batch</th><th>Supplier</th><th>Bahan</th><th>Tgl Bongkar</th>
        <th class="num">Masuk</th><th class="num">Terpakai</th><th class="num">Sisa</th><th>Status</th></tr></thead>
    <tbody>
        @forelse($analisa['bahanPerBatch'] as $row)
        <tr>
            <td>{{ $row['batch_number'] }}</td><td>{{ $row['supplier_code'] }}</td><td>{{ $row['subgroup'] }}</td>
            <td>{{ $row['tgl_bongkar'] }}</td>
            <td class="num">{{ number_format($row['masuk_kg'], 2) }}</td>
            <td class="num">{{ number_format($row['terpakai_kg'], 2) }}</td>
            <td class="num">{{ number_format($row['sisa_kg'], 2) }}</td>
            <td>{{ $row['status'] }}</td>
        </tr>
        @empty
        <tr><td colspan="8">Belum ada batch.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>3. Ringkasan per Tahap</h2>
<table>
    <thead><tr><th>Tahap</th><th class="ctr">Sesi</th><th class="num">Masuk</th><th class="num">Hasil</th>
        <th class="num">Limbah</th><th class="num">Yield</th><th class="num">Standar</th></tr></thead>
    <tbody>
        @forelse($analisa['perTahap'] as $row)
        <tr class="{{ $row['standard_min'] !== null && $row['yield_pct'] < $row['standard_min'] ? 'bad' : '' }}">
            <td>{{ $row['stage'] }}</td><td class="ctr">{{ $row['sesi'] }}</td>
            <td class="num">{{ number_format($row['input_kg'], 2) }}</td>
            <td class="num">{{ number_format($row['output_kg'], 2) }}</td>
            <td class="num">{{ number_format($row['waste_kg'], 2) }}</td>
            <td class="num">{{ number_format($row['yield_pct'], 1) }}%</td>
            <td class="num">{{ $row['standard_min'] !== null ? number_format($row['standard_min'], 1) . '%' : '-' }}</td>
        </tr>
        @empty
        <tr><td colspan="7">Belum ada sesi produksi.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>4. Sesi Produksi</h2>
<table>
    <thead><tr><th>Kode</th><th>Tahap</th><th>Tanggal</th><th>Operator</th>
        <th class="num">Masuk</th><th class="num">Hasil</th><th class="num">Limbah</th><th class="num">Yield</th></tr></thead>
    <tbody>
        @forelse($analisa['sesi'] as $row)
        <tr>
            <td>{{ $row['code'] }}</td><td>{{ $row['stage'] }}</td><td>{{ $row['date'] }}</td><td>{{ $row['operator'] }}</td>
            <td class="num">{{ number_format($row['input_kg'], 2) }}</td>
            <td class="num">{{ number_format($row['output_kg'], 2) }}</td>
            <td class="num">{{ number_format($row['waste_kg'], 2) }}</td>
            <td class="num">{{ number_format($row['yield_pct'], 1) }}%</td>
        </tr>
        @empty
        <tr><td colspan="8">Belum ada sesi.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>5. Target PO vs Packing</h2>
<table>
    <thead><tr><th>Produk</th><th class="num">Target kg</th><th class="ctr">Target Box</th>
        <th class="num">Packing kg</th><th class="ctr">Box</th><th class="num">Selisih</th><th>Status</th></tr></thead>
    <tbody>
        @foreach($analisa['targetVsPacking'] as $row)
        <tr>
            <td>{{ $row['produk'] }}</td>
            <td class="num">{{ number_format($row['target_kg'], 2) }}</td>
            <td class="ctr">{{ $row['target_box'] }}</td>
            <td class="num">{{ number_format($row['packing_kg'], 2) }}</td>
            <td class="ctr">{{ $row['packing_box'] }}</td>
            <td class="num">{{ number_format($row['selisih_kg'], 2) }}</td>
            <td>{{ $row['status'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>
