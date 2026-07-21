@props(['name', 'size' => 15])

@php
/**
 * Lucide-style outline icons. Each icon is a list of SVG elements:
 * ['path'|'circle'|'rect'|'line'|'polyline'|'polygon', attributes...]
 */
$icons = [
    'home' => [
        ['path', 'd' => 'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'],
        ['polyline', 'points' => '9 22 9 12 15 12 15 22'],
    ],
    'layers' => [
        ['polygon', 'points' => '12 2 2 7 12 12 22 7 12 2'],
        ['polyline', 'points' => '2 17 12 22 22 17'],
        ['polyline', 'points' => '2 12 12 17 22 12'],
    ],
    'briefcase' => [
        ['rect', 'width' => '20', 'height' => '14', 'x' => '2', 'y' => '7', 'rx' => '2', 'ry' => '2'],
        ['path', 'd' => 'M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16'],
    ],
    'calendar' => [
        ['rect', 'width' => '18', 'height' => '18', 'x' => '3', 'y' => '4', 'rx' => '2', 'ry' => '2'],
        ['line', 'x1' => '16', 'x2' => '16', 'y1' => '2', 'y2' => '6'],
        ['line', 'x1' => '8', 'x2' => '8', 'y1' => '2', 'y2' => '6'],
        ['line', 'x1' => '3', 'x2' => '21', 'y1' => '10', 'y2' => '10'],
    ],
    'check-circle' => [
        ['circle', 'cx' => '12', 'cy' => '12', 'r' => '10'],
        ['path', 'd' => 'm9 12 2 2 4-4'],
    ],
    'graduation-cap' => [
        ['path', 'd' => 'M22 10v6M2 10l10-5 10 5-10 5z'],
        ['path', 'd' => 'M6 12v5c3 3 9 3 12 0v-5'],
    ],
    'dollar-sign' => [
        ['line', 'x1' => '12', 'x2' => '12', 'y1' => '2', 'y2' => '22'],
        ['path', 'd' => 'M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
    ],
    'credit-card' => [
        ['rect', 'width' => '22', 'height' => '16', 'x' => '1', 'y' => '4', 'rx' => '2', 'ry' => '2'],
        ['line', 'x1' => '1', 'x2' => '23', 'y1' => '10', 'y2' => '10'],
    ],
    'file-text' => [
        ['path', 'd' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'],
        ['polyline', 'points' => '14 2 14 8 20 8'],
        ['line', 'x1' => '16', 'x2' => '8', 'y1' => '13', 'y2' => '13'],
        ['line', 'x1' => '16', 'x2' => '8', 'y1' => '17', 'y2' => '17'],
        ['line', 'x1' => '10', 'x2' => '8', 'y1' => '9', 'y2' => '9'],
    ],
    'bell' => [
        ['path', 'd' => 'M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9'],
        ['path', 'd' => 'M10.3 21a1.94 1.94 0 0 0 3.4 0'],
    ],
    'bar-chart' => [
        ['line', 'x1' => '12', 'x2' => '12', 'y1' => '20', 'y2' => '10'],
        ['line', 'x1' => '18', 'x2' => '18', 'y1' => '20', 'y2' => '4'],
        ['line', 'x1' => '6', 'x2' => '6', 'y1' => '20', 'y2' => '14'],
    ],
    'settings' => [
        ['path', 'd' => 'M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z'],
        ['circle', 'cx' => '12', 'cy' => '12', 'r' => '3'],
    ],
    'log-out' => [
        ['path', 'd' => 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4'],
        ['polyline', 'points' => '16 17 21 12 16 7'],
        ['line', 'x1' => '21', 'x2' => '9', 'y1' => '12', 'y2' => '12'],
    ],
    'chevron-down' => [
        ['polyline', 'points' => '6 9 12 15 18 9'],
    ],
    'chevron-left' => [
        ['polyline', 'points' => '15 18 9 12 15 6'],
    ],
    'chevron-right' => [
        ['polyline', 'points' => '9 18 15 12 9 6'],
    ],
    'arrow-left' => [
        ['line', 'x1' => '19', 'x2' => '5', 'y1' => '12', 'y2' => '12'],
        ['polyline', 'points' => '12 19 5 12 12 5'],
    ],
    'eye' => [
        ['path', 'd' => 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z'],
        ['circle', 'cx' => '12', 'cy' => '12', 'r' => '3'],
    ],
    'plus' => [
        ['line', 'x1' => '12', 'x2' => '12', 'y1' => '5', 'y2' => '19'],
        ['line', 'x1' => '5', 'x2' => '19', 'y1' => '12', 'y2' => '12'],
    ],
    'printer' => [
        ['polyline', 'points' => '6 9 6 2 18 2 18 9'],
        ['path', 'd' => 'M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2'],
        ['rect', 'width' => '12', 'height' => '8', 'x' => '6', 'y' => '14'],
    ],
    'download' => [
        ['path', 'd' => 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4'],
        ['polyline', 'points' => '7 10 12 15 17 10'],
        ['line', 'x1' => '12', 'x2' => '12', 'y1' => '15', 'y2' => '3'],
    ],
    'upload' => [
        ['path', 'd' => 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4'],
        ['polyline', 'points' => '17 8 12 3 7 8'],
        ['line', 'x1' => '12', 'x2' => '12', 'y1' => '3', 'y2' => '15'],
    ],
    'search' => [
        ['circle', 'cx' => '11', 'cy' => '11', 'r' => '8'],
        ['line', 'x1' => '21', 'x2' => '16.65', 'y1' => '21', 'y2' => '16.65'],
    ],
    'message-square' => [
        ['path', 'd' => 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'],
    ],
    'users' => [
        ['path', 'd' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2'],
        ['circle', 'cx' => '9', 'cy' => '7', 'r' => '4'],
        ['path', 'd' => 'M22 21v-2a4 4 0 0 0-3-3.87'],
        ['path', 'd' => 'M16 3.13a4 4 0 0 1 0 7.75'],
    ],
    'user-plus' => [
        ['path', 'd' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2'],
        ['circle', 'cx' => '9', 'cy' => '7', 'r' => '4'],
        ['line', 'x1' => '19', 'x2' => '19', 'y1' => '8', 'y2' => '14'],
        ['line', 'x1' => '22', 'x2' => '16', 'y1' => '11', 'y2' => '11'],
    ],
    'bus' => [
        ['path', 'd' => 'M19 17h2l.64-2.54A2 2 0 0 0 19.7 12H4.3a2 2 0 0 0-1.94 2.46L3 17h2'],
        ['path', 'd' => 'M14 17H9'],
        ['circle', 'cx' => '6.5', 'cy' => '17.5', 'r' => '2.5'],
        ['circle', 'cx' => '16.5', 'cy' => '17.5', 'r' => '2.5'],
        ['path', 'd' => 'M4 12V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v6'],
        ['path', 'd' => 'M8 4v3'],
        ['path', 'd' => 'M16 4v3'],
        ['path', 'd' => 'M4 10h16'],
    ],
    'layout-grid' => [
        ['rect', 'width' => '7', 'height' => '7', 'x' => '3', 'y' => '3', 'rx' => '1'],
        ['rect', 'width' => '7', 'height' => '7', 'x' => '14', 'y' => '3', 'rx' => '1'],
        ['rect', 'width' => '7', 'height' => '7', 'x' => '14', 'y' => '14', 'rx' => '1'],
        ['rect', 'width' => '7', 'height' => '7', 'x' => '3', 'y' => '14', 'rx' => '1'],
    ],
];

$shapes = $icons[$name] ?? [
    ['circle', 'cx' => '12', 'cy' => '12', 'r' => '10'],
    ['line', 'x1' => '12', 'x2' => '12', 'y1' => '8', 'y2' => '12'],
    ['line', 'x1' => '12', 'x2' => '12.01', 'y1' => '16', 'y2' => '16'],
];
@endphp

<svg xmlns="http://www.w3.org/2000/svg" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>
    @foreach ($shapes as $shape)
        @php
            $tag = array_shift($shape);
        @endphp
        @if ($tag === 'path')
            <path d="{{ $shape['d'] }}" />
        @elseif ($tag === 'circle')
            <circle cx="{{ $shape['cx'] }}" cy="{{ $shape['cy'] }}" r="{{ $shape['r'] }}" />
        @elseif ($tag === 'rect')
            <rect width="{{ $shape['width'] }}" height="{{ $shape['height'] }}" x="{{ $shape['x'] }}" y="{{ $shape['y'] }}" @if(isset($shape['rx'])) rx="{{ $shape['rx'] }}" @endif @if(isset($shape['ry'])) ry="{{ $shape['ry'] }}" @endif />
        @elseif ($tag === 'line')
            <line x1="{{ $shape['x1'] }}" x2="{{ $shape['x2'] }}" y1="{{ $shape['y1'] }}" y2="{{ $shape['y2'] }}" />
        @elseif ($tag === 'polyline')
            <polyline points="{{ $shape['points'] }}" />
        @elseif ($tag === 'polygon')
            <polygon points="{{ $shape['points'] }}" />
        @endif
    @endforeach
</svg>
