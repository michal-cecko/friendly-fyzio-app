<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Resolves icon names chosen in the admin icon picker into renderable inline
 * SVG. The picker stores blade-icons names (e.g. "lucide-calendar",
 * "heroicon-o-star"); legacy content stored bare lucide names ("calendar"),
 * which are assumed to be lucide for backwards compatibility.
 */
class Icon
{
    /**
     * Normalise a stored icon value to a blade-icons identifier, or null when
     * empty/unresolvable.
     */
    public static function name(?string $icon): ?string
    {
        if (blank($icon)) {
            return null;
        }

        return Str::startsWith($icon, ['lucide-', 'heroicon-']) ? $icon : 'lucide-'.$icon;
    }

    /**
     * Render an icon as inline SVG. Returns an empty string when the name is
     * blank or fails to resolve, so it is always safe to echo in Blade.
     */
    public static function render(?string $icon, string $class = ''): HtmlString
    {
        $name = self::name($icon);

        if ($name === null) {
            return new HtmlString('');
        }

        return new HtmlString(rescue(fn (): string => svg($name, $class)->toHtml(), '', false));
    }
}
