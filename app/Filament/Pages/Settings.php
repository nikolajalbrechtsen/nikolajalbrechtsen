<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use UnitEnum;

class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Site settings';

    protected static ?string $navigationLabel = 'Settings';

    /** All setting keys this page manages. */
    protected const KEYS = [
        'site_name', 'site_tagline', 'site_description', 'logo', 'favicon', 'primary_color',
        'footer_text', 'contact_email', 'social_links', 'announcement',
        'registration_enabled', 'email_verification_required',
        'google_login_enabled', 'google_client_id', 'google_client_secret',
        'github_login_enabled', 'github_client_id', 'github_client_secret',
        'blog_enabled', 'blog_title', 'blog_intro', 'meta_image', 'analytics_head',
    ];

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('manage_settings');
    }

    public function mount(): void
    {
        $values = [];
        foreach (self::KEYS as $key) {
            // Never send secrets to the browser; a blank field keeps the stored value.
            $values[$key] = Setting::isSecret($key) ? null : Setting::get($key);
        }
        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('settings')->persistTabInQueryString()->tabs([
                    Tab::make('General')->icon(Heroicon::OutlinedGlobeAlt)->schema([
                        TextInput::make('site_name')->required(),
                        TextInput::make('site_tagline'),
                        Textarea::make('site_description')->rows(2)->helperText('Used for search engines and social sharing.'),
                        TextInput::make('contact_email')->email()->helperText('Where contact form notifications go.'),
                        TextInput::make('announcement')->helperText('Optional banner shown at the top of every page. Leave empty to hide.'),
                        TextInput::make('footer_text'),
                    ]),

                    Tab::make('Branding')->icon(Heroicon::OutlinedSwatch)->schema([
                        Grid::make(2)->schema([
                            FileUpload::make('logo')->image()->disk('public')->directory('branding')
                                ->helperText('Shown in the header. SVG or PNG with transparent background works best.'),
                            FileUpload::make('favicon')->image()->disk('public')->directory('branding'),
                        ]),
                        ColorPicker::make('primary_color')->helperText('Accent color used for buttons and links on the website.'),
                        FileUpload::make('meta_image')->label('Social sharing image')->image()->disk('public')->directory('branding')
                            ->helperText('1200×630 image shown when the site is shared on social media.'),
                    ]),

                    Tab::make('Social links')->icon(Heroicon::OutlinedLink)->schema([
                        Repeater::make('social_links')->hiddenLabel()
                            ->schema([
                                Select::make('platform')->options([
                                    'github' => 'GitHub', 'linkedin' => 'LinkedIn', 'x' => 'X / Twitter',
                                    'instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube',
                                    'mastodon' => 'Mastodon', 'email' => 'Email', 'website' => 'Website',
                                ])->required(),
                                TextInput::make('url')->required(),
                            ])
                            ->columns(2)
                            ->reorderable()
                            ->addActionLabel('Add link'),
                    ]),

                    Tab::make('Users & login')->icon(Heroicon::OutlinedUserGroup)->schema([
                        Toggle::make('registration_enabled')->label('Allow new users to sign up'),
                        Toggle::make('email_verification_required')->label('Require email verification')
                            ->helperText('Requires working mail settings (MAIL_* in .env).'),

                        Section::make('Google login')->collapsible()->schema([
                            Toggle::make('google_login_enabled')->label('Enable "Log in with Google"')->live(),
                            Text::make(fn () => new HtmlString('Create credentials at <a class="underline" target="_blank" href="https://console.cloud.google.com/apis/credentials">Google Cloud Console</a>. Redirect URI: <code>'.url('/auth/google/callback').'</code>')),
                            TextInput::make('google_client_id')->label('Client ID')->required(fn (Get $get) => $get('google_login_enabled')),
                            TextInput::make('google_client_secret')->label('Client secret')->password()->revealable()
                                ->placeholder(fn () => Setting::get('google_client_secret') ? '•••••••• (saved — leave empty to keep)' : ''),
                        ]),

                        Section::make('GitHub login')->collapsible()->schema([
                            Toggle::make('github_login_enabled')->label('Enable "Log in with GitHub"')->live(),
                            Text::make(fn () => new HtmlString('Create an OAuth app at <a class="underline" target="_blank" href="https://github.com/settings/developers">GitHub developer settings</a>. Callback URL: <code>'.url('/auth/github/callback').'</code>')),
                            TextInput::make('github_client_id')->label('Client ID')->required(fn (Get $get) => $get('github_login_enabled')),
                            TextInput::make('github_client_secret')->label('Client secret')->password()->revealable()
                                ->placeholder(fn () => Setting::get('github_client_secret') ? '•••••••• (saved — leave empty to keep)' : ''),
                        ]),
                    ]),

                    Tab::make('Blog')->icon(Heroicon::OutlinedNewspaper)->schema([
                        Toggle::make('blog_enabled')->label('Show the blog'),
                        TextInput::make('blog_title')->placeholder('Blog'),
                        Textarea::make('blog_intro')->rows(2),
                    ]),

                    Tab::make('Advanced')->icon(Heroicon::OutlinedCodeBracket)->schema([
                        Textarea::make('analytics_head')
                            ->label('Extra code in <head>')
                            ->helperText('E.g. analytics script (Plausible, Umami, Google Analytics). Rendered as-is on every public page.')
                            ->rows(6)
                            ->extraInputAttributes(['class' => 'font-mono text-sm']),
                    ]),
                ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save settings')->submit('save')->keyBindings(['mod+s']),
                    ])->sticky(),
                ]),
        ]);
    }

    public function save(): void
    {
        Setting::setMany($this->form->getState());

        Notification::make()->title('Settings saved')->success()->send();
    }
}
