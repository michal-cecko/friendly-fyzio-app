<?php

namespace Tests\Feature\Cms;

use Filament\Forms\Components\RichEditor;
use ReflectionProperty;
use Tests\TestCase;

class RichEditorToolbarTest extends TestCase
{
    public function test_global_rich_editor_excludes_blockquote_and_codeblock(): void
    {
        // The global RichEditor::configureUsing() in AppServiceProvider runs on make().
        $editor = RichEditor::make('content');

        $property = new ReflectionProperty(RichEditor::class, 'toolbarButtons');
        $buttons = collect($property->getValue($editor))->flatten()->all();

        $this->assertNotContains('blockquote', $buttons);
        $this->assertNotContains('codeBlock', $buttons);

        // Other tools remain.
        $this->assertContains('bold', $buttons);
        $this->assertContains('bulletList', $buttons);
    }
}
