<div class="mb-3">
    <div style="padding-left: 90px; padding-right: 90px; display: flex; justify-content: space-between; align-items: baseline;">
        <span class="font-bold uppercase" style="font-size: 14px;">{{ $bookTitle ?? 'BUKU STOK KHUSUS' }}</span>
        <span style="font-size: 12px; width: 140px;"><span style="display: inline-block; width: 52px;">Model</span> : {{ $bsModel ?? 'BS 1' }}</span>
    </div>
    <div style="font-size: 12px; padding-left: 90px; padding-right: 90px; display: flex; justify-content: space-between;">
        <span><span style="display: inline-block; width: 40px;">Bulan</span> : {{ $bulan }}</span>
        <span style="width: 140px;"><span style="display: inline-block; width: 52px;">Halaman</span> : {{ $pageDisplay }}</span>
    </div>
    <div style="font-size: 12px; padding-left: 90px; padding-right: 90px; display: flex; justify-content: space-between;">
        <span><span style="display: inline-block; width: 40px;">Tahun</span> : {{ $tahun }}</span>
        <span style="width: 140px;"><span style="display: inline-block; width: 52px;">Model</span> : {{ $model }}</span>
    </div>
</div>
