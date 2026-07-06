<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>{{ $code }}</title>
    <style>
        @page {
            size: A4;
            margin: 20mm 20mm 20mm 27mm;
        }

        html, body {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: DejaVu Serif, "Times New Roman", serif;
            color: #111111;
            font-size: 12pt;
            line-height: 1.25;
        }

        .page {
            width: 100%;
        }

        .header-table,
        .signature-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .header-table td,
        .signature-grid td {
            vertical-align: top;
            padding: 0;
        }

        .org-block {
            text-align: center;
            font-size: 12pt;
            line-height: 1.15;
            font-weight: 700;
            text-transform: uppercase;
        }

        .org-block div + div {
            margin-top: 1mm;
        }

        .date-block {
            text-align: right;
            font-style: italic;
            font-size: 12pt;
            line-height: 1.15;
            padding-top: 1mm;
        }

        .title {
            margin: 3mm 0 1mm;
            text-align: center;
            font-size: 18pt;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 1.1;
        }

        .subject {
            margin: 0 0 3mm;
            text-align: center;
            font-size: 12.5pt;
            font-style: italic;
            font-weight: 700;
            line-height: 1.15;
        }

        .recipient-block {
            margin: 0 0 3mm;
            font-size: 12pt;
            line-height: 1.25;
        }

        .recipient-label {
            font-weight: 700;
            font-style: italic;
        }

        .recipient-list {
            margin-top: 0.5mm;
            padding-left: 10mm;
        }

        .recipient-list div {
            text-indent: -4mm;
            padding-left: 4mm;
        }

        .body {
            text-align: justify;
        }

        .body p {
            margin: 0 0 2mm;
            text-indent: 10mm;
        }

        .body p:last-child {
            margin-bottom: 0;
        }

        .body em {
            font-style: italic;
        }

        .section-title {
            margin: 4mm 0 2mm;
            font-size: 12pt;
            font-weight: 700;
        }

        .subsection-title {
            margin: 2mm 0 1mm;
            font-size: 12pt;
            font-weight: 700;
        }

        .request-item {
            margin: 0 0 1.5mm;
            text-indent: 0;
        }

        .empty {
            color: #666666;
            font-style: italic;
        }

        .signature-grid {
            margin-top: 12mm;
            page-break-inside: avoid;
        }

        .signature-grid td {
            text-align: center;
            padding: 0 2mm;
        }

        .signature-block {
            page-break-inside: avoid;
        }

        .signature-block .role {
            min-height: 12mm;
            font-size: 12pt;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 1.15;
            white-space: pre-line;
        }

        .signature-block .stamp {
            min-height: 22mm;
            margin: 0 10mm 2mm;
            text-align: center;
        }

        .signature-block .stamp img {
            display: inline-block;
            max-height: 22mm;
            max-width: 55mm;
            object-fit: contain;
        }

        .signature-block .line {
            height: 10mm;
            border-bottom: 1px solid #111111;
            margin: 0 10mm 2mm;
        }

        .signature-block .name {
            min-height: 6mm;
            font-size: 12pt;
            font-weight: 700;
            line-height: 1.15;
        }

        .signature-block .note {
            font-size: 10.5pt;
            font-style: italic;
            line-height: 1.15;
        }
    </style>
</head>
<body>
@php
    $creatorDept = trim((string) ($submission->creator?->department?->dept_name ?? ''));

    $orgLines = array_values(array_filter([
        'ĐOÀN THANH NIÊN - HỘI SINH VIÊN',
        $creatorDept !== '' ? mb_strtoupper($creatorDept, 'UTF-8') : 'ĐƠN VỊ TRÌNH',
        '***',
    ]));

    $issuedAt = $submission->created_at ? \Carbon\Carbon::parse($submission->created_at) : now();
    $dateLine = sprintf(
        'Hà Nội, ngày %s tháng %s năm %s',
        $issuedAt->format('d'),
        $issuedAt->format('m'),
        $issuedAt->format('Y')
    );

    $rawContent = trim((string) ($submission->content ?? ''));
    $contentParagraphs = preg_split("/(\r\n|\r|\n){2,}/u", $rawContent) ?: [];
    $contentParagraphs = array_values(array_filter(array_map('trim', $contentParagraphs), function ($value) {
        return $value !== '';
    }));

    if (empty($contentParagraphs) && $rawContent !== '') {
        $contentParagraphs = [$rawContent];
    }

    $hasGreeting = preg_match('/^\s*(kính gửi|kinh gui)\b/ui', $rawContent) === 1;

    $recipientLines = collect($submission->steps ?? [])
        ->pluck('department.dept_name')
        ->filter()
        ->unique()
        ->values();

    $subject = trim(preg_replace('/\s+/u', ' ', (string) ($submission->title ?? '')));
    if ($subject === '') {
        $subject = 'Tờ trình';
    }

    $subjectText = trim(preg_replace('/^\s*(tờ trình|to trinh|v\/v|về việc)\s*[:\-]?\s*/iu', '', $subject));
    if ($subjectText === '') {
        $subjectText = $subject;
    }

    $assets = collect($submission->assetRequests ?? []);
    $locations = collect($submission->submissionLocations ?? []);

    $loadSignature = function (?string $path): ?string {
        if (!$path) {
            return null;
        }

        $fullPath = storage_path('app/public/' . ltrim($path, '/'));
        if (!file_exists($fullPath)) {
            return null;
        }

        $mime = mime_content_type($fullPath) ?: 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fullPath));
    };

    $stepSigners = collect($submission->steps ?? [])
        ->map(function ($step) use ($loadSignature) {
            $latestApproval = collect($step->approvalLogs ?? [])
                ->filter(function ($log) {
                    return ($log->action ?? null) === 'approved';
                })
                ->sortByDesc('created_at')
                ->first();

            $approver = $latestApproval?->approver;
            $deptName = trim((string) ($step->department?->dept_name ?? ''));

            if ($deptName === '') {
                return null;
            }

            return [
                'role' => mb_strtoupper($deptName, 'UTF-8'),
                'name' => trim((string) ($approver?->full_name ?? '')),
                'signature' => $loadSignature($approver?->signature_path),
            ];
        })
        ->filter()
        ->values();

    $creatorRole = 'NGƯỜI LẬP TỜ TRÌNH';
    $upperCreatorDept = $creatorDept !== '' ? mb_strtoupper($creatorDept, 'UTF-8') : '';
    if ($upperCreatorDept !== '' && (str_contains($upperCreatorDept, 'CLB') || str_contains($upperCreatorDept, 'CÂU LẠC BỘ'))) {
        $creatorRole = 'TM. ' . $upperCreatorDept . "\nCHỦ NHIỆM";
    }

    $creatorSignature = $loadSignature($submission->creator?->signature_path);

    $approvalCount = $stepSigners->count();
    $signatureColumns = ($approvalCount + 1) <= 2 ? 2 : 3;
    $fillersNeeded = ($signatureColumns - ((($approvalCount + 1) % $signatureColumns))) % $signatureColumns;

    $signatureBlocks = $stepSigners
        ->concat(array_fill(0, $fillersNeeded, null))
        ->push([
            'role' => $creatorRole,
            'name' => trim((string) ($submission->creator?->full_name ?? '')),
            'signature' => $creatorSignature,
        ])
        ->values();

    $signatureRows = $signatureBlocks->chunk($signatureColumns);
@endphp

<div class="page">
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <div class="org-block">
                    @forelse ($orgLines as $line)
                        <div>{{ $line }}</div>
                    @empty
                        <div>ĐOÀN THANH NIÊN - HỘI SINH VIÊN</div>
                        <div>ĐƠN VỊ TRÌNH</div>
                        <div>***</div>
                    @endforelse
                </div>
            </td>
            <td style="width: 45%;">
                <div class="date-block">{{ $dateLine }}</div>
            </td>
        </tr>
    </table>

    <div class="title">TỜ TRÌNH</div>
    <div class="subject">V/v {{ $subjectText }}</div>

    @if (!$hasGreeting && $recipientLines->isNotEmpty())
        <div class="recipient-block">
            <span class="recipient-label">Kính gửi:</span>
            <div class="recipient-list">
                @foreach ($recipientLines as $index => $recipient)
                    <div>- {{ $recipient }}{{ $loop->last ? '.' : ';' }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="body">
        @forelse ($contentParagraphs as $paragraph)
            @php
                $trimmed = trim((string) $paragraph);
                $isBullet = preg_match('/^(\*|\d+\.)\s/u', $trimmed) === 1;
                $isItalic = preg_match('/(kính trình:|trân trọng cảm ơn|chân thành cảm ơn|xin chân thành cảm ơn)/iu', $trimmed) === 1;
            @endphp
            <p>
                @if ($isItalic)
                    <em>{!! nl2br(e($trimmed)) !!}</em>
                @elseif ($isBullet)
                    <strong>{!! nl2br(e($trimmed)) !!}</strong>
                @else
                    {!! nl2br(e($trimmed)) !!}
                @endif
            </p>
        @empty
            <p class="empty">Không có nội dung.</p>
        @endforelse
    </div>

    @if ($assets->isNotEmpty() || $locations->isNotEmpty())
        <div class="section-title">Nội dung kèm theo</div>

        @if ($assets->isNotEmpty())
            <div class="subsection-title">Danh mục tài sản</div>
            <div class="body">
                @foreach ($assets as $index => $assetRequest)
                    @php
                        $assetName = $assetRequest->asset?->asset_name ?? $assetRequest->asset?->name ?? 'Tài sản';
                        $borrowDate = $assetRequest->expected_borrow_date
                            ? \Carbon\Carbon::parse($assetRequest->expected_borrow_date)->format('d/m/Y H:i')
                            : null;
                        $returnDate = $assetRequest->expected_return_date
                            ? \Carbon\Carbon::parse($assetRequest->expected_return_date)->format('d/m/Y H:i')
                            : null;
                        $note = trim((string) ($assetRequest->note ?? ''));
                        $quantity = null;

                        if (preg_match('/SL:\s*(\d+)/i', $note, $matches)) {
                            $quantity = (int) $matches[1];
                        }
                    @endphp
                    <p class="request-item">
                        <strong>{{ $index + 1 }}.</strong>
                        <strong>{{ $assetName }}</strong>
                        @if ($quantity)
                            - SL: {{ $quantity }}
                        @endif
                        @if ($borrowDate)
                            - Dự kiến mượn: {{ $borrowDate }}
                        @endif
                        @if ($returnDate)
                            - Dự kiến trả: {{ $returnDate }}
                        @endif
                        @if ($note !== '')
                            - {{ $note }}
                        @endif
                    </p>
                @endforeach
            </div>
        @endif

        @if ($locations->isNotEmpty())
            <div class="subsection-title">Địa điểm / thời gian</div>
            <div class="body">
                @foreach ($locations as $index => $locationEntry)
                    @php
                        $locationName = $locationEntry->location?->location_name ?? $locationEntry->location?->name ?? 'Địa điểm';
                        $startTime = $locationEntry->start_time
                            ? \Carbon\Carbon::parse($locationEntry->start_time)->format('d/m/Y H:i')
                            : null;
                        $endTime = $locationEntry->end_time
                            ? \Carbon\Carbon::parse($locationEntry->end_time)->format('d/m/Y H:i')
                            : null;
                    @endphp
                    <p class="request-item">
                        <strong>{{ $index + 1 }}.</strong>
                        <strong>{{ $locationName }}</strong>
                        @if ($startTime || $endTime)
                            - {{ $startTime ?? '...' }} đến {{ $endTime ?? '...' }}
                        @endif
                    </p>
                @endforeach
            </div>
        @endif
    @endif

    <table class="signature-grid">
        @foreach ($signatureRows as $row)
            <tr>
                @foreach ($row as $block)
                    <td style="width: {{ 100 / $signatureColumns }}%;">
                        @if ($block)
                            <div class="signature-block">
                                <div class="role">{{ $block['role'] }}</div>
                                <div class="stamp">
                                    @if (!empty($block['signature']))
                                        <img src="{{ $block['signature'] }}" alt="Chữ ký">
                                    @endif
                                </div>
                                <div class="line"></div>
                                <div class="name">{{ $block['name'] }}</div>
                                <div class="note">Ký, ghi rõ họ tên</div>
                            </div>
                        @else
                            &nbsp;
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>
</div>
</body>
</html>
