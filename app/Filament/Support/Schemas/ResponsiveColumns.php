<?php

namespace App\Filament\Support\Schemas;

class ResponsiveColumns
{
    /**
     * Dense data sections: scale 1 → 2 → 3 → 4 columns based on the SECTION's
     * own width (container queries). Use together with ->gridContainer().
     */
    public const DENSE = ['default' => 1, '@sm' => 2, '@2xl' => 3, '@4xl' => 4];

    /** Medium sections (≈2–4 short fields): cap at 2 columns. */
    public const PAIR = ['default' => 1, '@md' => 2];
}
