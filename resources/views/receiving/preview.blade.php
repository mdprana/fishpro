@extends('layouts.app')
@section('title', 'Pratinjau CSV')
@section('breadcrumb')<li class="breadcrumb-item"><a href="{{ route('receiving.index') }}">Bahan Baku</a></li><li class="breadcrumb-item active">Pratinjau CSV</li>@endsection
@push('styles')
<style>
    .csv-preview-scroll :is(th,td):is(:nth-child(2),:nth-child(3),:nth-child(4),:nth-child(9),:nth-child(10)){white-space:nowrap}
</style>
@endpush
@section('content')
<div class="form-card">
    <h4>Pratinjau CSV penerimaan</h4>
    <p role="status">{{ count($preview['rows']) }} baris valid. Belum ada data disimpan.</p>
    @if($preview['errors'])
        <div class="alert alert-danger" role="alert">
            <p>Semua kesalahan harus diperbaiki sebelum konfirmasi.</p>
            <ul>@foreach($preview['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>
            @if($preview['remaining'])<p>{{ $preview['remaining'] }} kesalahan lainnya.</p>@endif
        </div>
    @endif
    <div class="table-responsive csv-preview-scroll" role="region" tabindex="0" aria-label="Baris penerimaan CSV, gulir horizontal untuk melihat semua kolom">
        <table class="table table-sm">
            <thead><tr><th>Baris</th><th>PO</th><th>Subgroup</th><th>Supplier</th><th>Berat (kg)</th><th>Wadah</th><th>Jumlah</th><th>Pisah</th><th>Tanggal</th><th>Dokumen</th></tr></thead>
            <tbody>@foreach($preview['rows'] as $row)
                <tr><td>{{ $row['line'] }}</td><td>{{ $row['po'] }}</td><td>{{ $row['subgroup'] }}</td><td>{{ $row['supplier'] }}</td><td>{{ $row['data']['weight_kg'] }}</td><td>{{ $row['data']['container_type'] }}</td><td>{{ $row['data']['container_count'] }}</td><td>{{ $row['data']['split_per_container'] ? 'Ya' : 'Tidak' }}</td><td>{{ $row['data']['received_date'] }}</td><td>{{ $row['data']['doc_number'] }}</td></tr>
            @endforeach</tbody>
        </table>
    </div>
    @if($envelope)
        <p>Konfirmasi berlaku 30 menit, hanya untuk akun ini. Referensi diperiksa ulang saat disimpan. Isi identik, termasuk urutan berbeda, tidak dapat diimpor lagi.</p>
        <form action="{{ route('receiving.import.commit') }}" method="POST" onsubmit="this.querySelector('button').disabled=true">
            @csrf
            <input type="hidden" name="envelope" value="{{ $envelope }}">
            <button class="btn btn-primary" type="submit">Konfirmasi semua penerimaan</button>
        </form>
    @endif
    <a class="btn btn-outline-secondary mt-3" href="{{ route('receiving.index') }}">Kembali</a>
</div>
@endsection
