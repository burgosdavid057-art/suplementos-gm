<?php
declare(strict_types=1);

class Icons {
    
    
    private const PATHS = [
        
        'check'   => '<path d="M5 13l4 4L19 7"/>',
        'x'       => '<path d="M6 18L18 6M6 6l12 12"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'pending' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'info'    => '<circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v5h1"/>',
        'alert'   => '<path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>',

        
        'cart'        => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 002 1.6h9.7a2 2 0 002-1.6L23 6H6"/>',
        'package'     => '<path d="M16.5 9.4L7.5 4.21M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/>',
        'truck'       => '<rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'bus'         => '<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.5 2.8C2 11 2 11.3 2 11.7V16c0 .6.4 1 1 1h2"/><circle cx="7"  cy="18" r="2"/><circle cx="17" cy="18" r="2"/><path d="M2 10h20"/>',
        'tag'         => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
        'wallet'      => '<path d="M20 12V8H6a2 2 0 010-4h12v4"/><path d="M4 6v12a2 2 0 002 2h14v-4"/><path d="M18 12a2 2 0 000 4h4v-4z"/>',
        'credit-card' => '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
        'sparkles'    => '<path d="M12 3l1.7 4.3L18 9l-4.3 1.7L12 15l-1.7-4.3L6 9l4.3-1.7L12 3z"/><path d="M19 14l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8.8-2z"/>',

        
        'user'      => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'building'  => '<rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 22V12h6v10M9 6h.01M15 6h.01M9 10h.01M15 10h.01"/>',
        'id-card'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8" cy="12" r="2"/><path d="M14 10h5M14 14h3M6 17c.7-1.2 1.9-2 3.5-2s2.8.8 3.5 2"/>',

        
        'mail'      => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 7L2 7"/>',
        'phone'     => '<path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.13.96.37 1.9.72 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.91.35 1.85.59 2.81.72A2 2 0 0122 16.92z"/>',
        'whatsapp'  => '<path fill="currentColor" stroke="none" d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>',
        'message'   => '<path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/>',
        'map-pin'   => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>',

        
        'home'      => '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><path d="M9 22V12h6v10"/>',
        'shopping-bag' => '<path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'menu'      => '<line x1="4" y1="7"  x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/>',
        'arrow-right' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'arrow-left'  => '<path d="M19 12H5M12 5l-7 7 7 7"/>',
        'refresh'   => '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/>',
        'plus'      => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',

        
        'pill'         => '<rect x="2" y="9" width="20" height="6" rx="3"/><path d="M12 9v6"/>',           
        'bone'         => '<path d="M18 2a3 3 0 00-3 3v.4l-5.85 5.85L8.6 11l-.4.55c-1.3-.1-2.6.5-3.4 1.3a3.3 3.3 0 00.4 5.1c-.8.9-1.4 2.2-1.3 3.4l-.55.4.55.55c-.1 1.2.5 2.5 1.3 3.4M19 6l-5 5"/>',
        'zap'          => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',                   
        'leaf'         => '<path d="M5 21c1-.5 8-3 11-7s5-9 5-12c0 0-7 .5-13 3S2 12 5 21z"/><path d="M5 21c0-7 5-13 12-13"/>', 
        'stethoscope'  => '<path d="M4 4v8a6 6 0 0012 0V4"/><circle cx="18" cy="18" r="3"/><path d="M10 14v4a4 4 0 008 0"/>',  
        'muscle'       => '<path d="M14 11a5 5 0 100 10 5 5 0 000-10zM6 7a3 3 0 014 0M5 18l-1 4M8 21l1-4"/>', 
        'shield'       => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',                       
        'horseshoe'    => '<path d="M6 4v7a6 6 0 0012 0V4M5 13h2M17 13h2M5 17h2M17 17h2M5 21h2M17 21h2"/>', 
        'star'         => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>', 
        'recycle'      => '<polyline points="14 16 18 20 22 16"/><polyline points="2 12 6 8 10 12"/><path d="M18 20V10a5 5 0 00-9-3M6 8v10a5 5 0 009 3"/>', 

        
        'lock'    => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>',
        'unlock'  => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 019.9-1"/>',

        
        'bar-chart' => '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6"  y1="20" x2="6"  y2="16"/><line x1="3" y1="20" x2="21" y2="20"/>',
        'folder'    => '<path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/>',
    ];

    
    public static function svg(string $name, string $cls = 'w-5 h-5', array $opts = []): string {
        $inner = self::PATHS[$name] ?? null;
        if ($inner === null) {
            
            $inner = self::PATHS['package'];
        }
        $stroke = $opts['stroke'] ?? '2';
        $fill   = $opts['fill']   ?? 'none';
        $cls    = htmlspecialchars($cls, ENT_QUOTES);
        
        if ($name === 'whatsapp') {
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="' . $cls . '" aria-hidden="true">' . $inner . '</svg>';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="' . htmlspecialchars($fill, ENT_QUOTES) . '" stroke="currentColor" stroke-width="' . htmlspecialchars($stroke, ENT_QUOTES) . '" stroke-linecap="round" stroke-linejoin="round" class="' . $cls . '" aria-hidden="true">' . $inner . '</svg>';
    }
}

if (!function_exists('icon')) {
    function icon(string $name, string $cls = 'w-5 h-5', array $opts = []): string {
        return Icons::svg($name, $cls, $opts);
    }
}
