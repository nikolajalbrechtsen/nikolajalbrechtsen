<?php

namespace App\Filament\Blocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;

/**
 * The content blocks you can drag onto a page.
 *
 * To add a new block:
 *   1. add a Block::make('my_block') here
 *   2. create resources/views/blocks/my_block.blade.php
 */
class PageBlocks
{
    /** Icons available for feature cards (Heroicons names). */
    public const ICONS = [
        'code-bracket' => 'Code', 'server' => 'Server', 'sparkles' => 'Sparkles',
        'rocket-launch' => 'Rocket', 'bolt' => 'Bolt', 'shield-check' => 'Shield',
        'cpu-chip' => 'Chip', 'cloud' => 'Cloud', 'paint-brush' => 'Paint brush',
        'light-bulb' => 'Light bulb', 'heart' => 'Heart', 'star' => 'Star',
        'globe-alt' => 'Globe', 'command-line' => 'Terminal', 'cube' => 'Cube',
        'chart-bar' => 'Chart', 'users' => 'Users', 'academic-cap' => 'Education',
        'briefcase' => 'Briefcase', 'camera' => 'Camera', 'wrench-screwdriver' => 'Tools',
    ];

    /** @return array<Block> */
    public static function all(): array
    {
        return [
            Block::make('hero')
                ->label('Hero banner')
                ->icon('heroicon-o-star')
                ->schema([
                    TextInput::make('eyebrow')->label('Small text above heading'),
                    TextInput::make('heading')->required(),
                    Textarea::make('subheading')->rows(2),
                    Grid::make(2)->schema([
                        TextInput::make('primary_label')->label('Main button text'),
                        TextInput::make('primary_url')->label('Main button link'),
                        TextInput::make('secondary_label')->label('Second button text'),
                        TextInput::make('secondary_url')->label('Second button link'),
                    ]),
                    FileUpload::make('image')->image()->disk('public')->directory('pages')->imageEditor(),
                ]),

            Block::make('rich_text')
                ->label('Text')
                ->icon('heroicon-o-document-text')
                ->schema([
                    TextInput::make('heading'),
                    RichEditor::make('content')
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('pages')
                        ->fileAttachmentsVisibility('public'),
                ]),

            Block::make('features')
                ->label('Feature cards')
                ->icon('heroicon-o-squares-2x2')
                ->schema([
                    TextInput::make('heading'),
                    Textarea::make('subheading')->rows(2),
                    Repeater::make('items')
                        ->schema([
                            Select::make('icon')->options(self::ICONS)->searchable()->default('sparkles'),
                            TextInput::make('title')->required(),
                            Textarea::make('text')->rows(2),
                        ])
                        ->columns(3)
                        ->collapsible()
                        ->itemLabel(fn (array $state) => $state['title'] ?? null)
                        ->defaultItems(3),
                ]),

            Block::make('image_text')
                ->label('Image + text')
                ->icon('heroicon-o-photo')
                ->schema([
                    FileUpload::make('image')->image()->disk('public')->directory('pages')->imageEditor()->required(),
                    TextInput::make('heading'),
                    RichEditor::make('content'),
                    Grid::make(2)->schema([
                        TextInput::make('button_label'),
                        TextInput::make('button_url'),
                    ]),
                    Toggle::make('image_right')->label('Image on the right'),
                ]),

            Block::make('stats')
                ->label('Numbers / stats')
                ->icon('heroicon-o-chart-bar')
                ->schema([
                    TextInput::make('heading'),
                    Repeater::make('items')
                        ->schema([
                            TextInput::make('value')->required()->placeholder('10+'),
                            TextInput::make('label')->required()->placeholder('Years of experience'),
                        ])
                        ->columns(2)
                        ->defaultItems(3),
                ]),

            Block::make('faq')
                ->label('FAQ')
                ->icon('heroicon-o-question-mark-circle')
                ->schema([
                    TextInput::make('heading')->default('Frequently asked questions'),
                    Repeater::make('items')
                        ->schema([
                            TextInput::make('question')->required(),
                            Textarea::make('answer')->rows(3)->required(),
                        ])
                        ->collapsible()
                        ->itemLabel(fn (array $state) => $state['question'] ?? null),
                ]),

            Block::make('cta')
                ->label('Call to action')
                ->icon('heroicon-o-megaphone')
                ->schema([
                    TextInput::make('heading')->required(),
                    Textarea::make('text')->rows(2),
                    Grid::make(2)->schema([
                        TextInput::make('button_label'),
                        TextInput::make('button_url'),
                    ]),
                ]),

            Block::make('upcoming_events')
                ->label('Upcoming events')
                ->icon('heroicon-o-calendar-days')
                ->schema([
                    TextInput::make('heading')->default('Upcoming events'),
                    TextInput::make('count')->numeric()->default(5)->minValue(1)->maxValue(20),
                    Textarea::make('empty_text')->rows(1)->default('No upcoming events right now.')
                        ->helperText('Shown when there are no public events. Events come from Admin → Calendar ("Show on the website").'),
                ]),

            Block::make('latest_posts')
                ->label('Latest blog posts')
                ->icon('heroicon-o-newspaper')
                ->schema([
                    TextInput::make('heading')->default('Latest posts'),
                    TextInput::make('count')->numeric()->default(3)->minValue(1)->maxValue(12),
                ]),

            Block::make('contact_form')
                ->label('Contact form')
                ->icon('heroicon-o-envelope')
                ->schema([
                    TextInput::make('heading')->default('Get in touch'),
                    Textarea::make('text')->rows(2),
                ]),

            Block::make('html')
                ->label('Custom HTML')
                ->icon('heroicon-o-code-bracket')
                ->schema([
                    Textarea::make('html')
                        ->label('HTML (rendered as-is)')
                        ->rows(10)
                        ->extraInputAttributes(['class' => 'font-mono text-sm']),
                ]),
        ];
    }
}
