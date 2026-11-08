<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $dataset['title'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 2px; }
        .meta { color: #555; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #555; padding: 4px 6px; text-align: left; }
        th { background-color: #eee; }
    </style>
</head>
<body>
    <h1>{{ $dataset['title'] }}</h1>
    <p class="meta">Total {{ number_format($dataset['total']) }} baris &middot; Dibuat {{ $dataset['generated_at'] }}</p>

    <table>
        <thead>
            <tr>
                @foreach ($dataset['headings'] as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($dataset['rows'] as $row)
                <tr>
                    @foreach ($dataset['headings'] as $heading)
                        <td>{{ $row[$heading] ?? '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
