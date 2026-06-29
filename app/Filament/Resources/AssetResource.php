<?php

namespace App\Filament\Resources;

use App\Enums\AssetCondition;
use App\Enums\NbhStatus;
use App\Filament\Resources\AssetResource\Pages;
use App\Filament\Resources\AssetResource\RelationManagers\AssetTransfersRelationManager;
use App\Models\Asset;
use App\Models\AssetAttribute;
use App\Models\AssetLocation;
use App\Models\Brand;
use App\Models\BusinessEntity;
use App\Models\Category;
use App\Models\CustomAssetAttribute;
use App\Models\User;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Carbon\Carbon;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid as ComponentsGrid;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section as ComponentsSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class AssetResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Asset::class;

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'export',
            'import',
        ];
    }

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    public static function getCategoryOptions(): array
    {
        return Cache::remember('asset_category_options', 300, function () {
            $categories = Category::whereNull('parent_id')
                ->with('children:id,name,parent_id')
                ->get(['id', 'name']);

            $options = [];
            foreach ($categories as $category) {
                if ($category->children->isNotEmpty()) {
                    $options[$category->name] = $category->children->pluck('name', 'id')->toArray();
                }
            }

            return $options;
        });
    }

    // Fungsi helper untuk mendapatkan tipe atribut
    public function getCustomAttributeType($customAttributeId)
    {
        $customAttribute = CustomAssetAttribute::find($customAttributeId);

        return $customAttribute ? $customAttribute->type : null;
    }

    protected static function shouldShowSaleAuditFields(callable $get): bool
    {
        return $get('condition_status') === AssetCondition::Sold->value
            || filled($get('sold_at'))
            || filled($get('sold_to'))
            || filled($get('sold_price'))
            || filled($get('sale_document_path'))
            || filled($get('sale_notes'));
    }

    protected static function hasSaleAuditRecord(Asset $record): bool
    {
        return filled($record->sold_at)
            || filled($record->sold_to)
            || filled($record->sold_price)
            || filled($record->sale_document_path)
            || filled($record->sale_notes);
    }

    protected static function resolveDocumentUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return Storage::url($path);
    }

    protected static function customAttributeType($customAttributeId): ?string
    {
        if (! filled($customAttributeId)) {
            return null;
        }

        return CustomAssetAttribute::find($customAttributeId)?->type;
    }

    protected static function attributeUsesType($customAttributeId, string $type): bool
    {
        return self::customAttributeType($customAttributeId) === $type;
    }

    protected static function customAttributeIsRequired($customAttributeId): bool
    {
        if (! filled($customAttributeId)) {
            return false;
        }

        return (bool) CustomAssetAttribute::find($customAttributeId)?->required;
    }

    protected static function prepareAttributeRelationshipData(array $data): array
    {
        $customAttribute = CustomAssetAttribute::find($data['custom_attribute_id'] ?? null);

        if ($customAttribute?->type === CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY) {
            $documentPath = $data['document_file_path'] ?? null;

            if (is_array($documentPath)) {
                $documentPath = reset($documentPath) ?: null;
            }

            $data['attribute_value'] = AssetAttribute::documentValue([
                'expires_at' => $data['document_expires_at'] ?? null,
                'document_number' => $data['document_number'] ?? null,
                'document_path' => $documentPath,
                'notes' => $data['document_notes'] ?? null,
            ]);
        } elseif (is_array($data['attribute_value'] ?? null)) {
            $data['attribute_value'] = null;
        }

        unset(
            $data['custom_attribute_label'],
            $data['document_expires_at'],
            $data['document_number'],
            $data['document_file_path'],
            $data['document_notes']
        );

        return $data;
    }

    protected static function attributeFormState(AssetAttribute $attribute): array
    {
        $customAttribute = $attribute->customAttribute ?? CustomAssetAttribute::find($attribute->custom_attribute_id);
        $attribute->setRelation('customAttribute', $customAttribute);
        $payload = $attribute->documentPayload();

        return [
            'custom_attribute_id' => $attribute->custom_attribute_id,
            'custom_attribute_label' => $customAttribute?->name,
            'attribute_value' => $attribute->isDocumentExpiryAttribute() ? null : $attribute->attribute_value,
            'document_expires_at' => $payload['expires_at'] ?? null,
            'document_number' => $payload['document_number'] ?? null,
            'document_file_path' => $payload['document_path'] ?? null,
            'document_notes' => $payload['notes'] ?? null,
        ];
    }

    protected static function documentStatusFromForm(callable $get): string
    {
        $customAttribute = CustomAssetAttribute::find($get('custom_attribute_id'));

        if (! $customAttribute) {
            return 'Pilih atribut dokumen terlebih dahulu.';
        }

        $attribute = new AssetAttribute([
            'attribute_value' => AssetAttribute::documentValue([
                'expires_at' => $get('document_expires_at'),
                'document_number' => $get('document_number'),
                'document_path' => $get('document_file_path'),
                'notes' => $get('document_notes'),
            ]),
        ]);
        $attribute->setRelation('customAttribute', $customAttribute);

        return $attribute->expiryReminderStatusLabelOn();
    }

    /**
     * @return array<int, Component>
     */
    public static function repairCompletionFormSchema(): array
    {
        return [
            Grid::make(2)
                ->schema([
                    DatePicker::make('nbh_reported_at')
                        ->label('Tanggal Insiden')
                        ->native(false)
                        ->helperText('Tanggal insiden NBH (terjaga, tidak diubah saat penyelesaian perbaikan).'),
                    Select::make('nbh_responsible_user_id')
                        ->label('Penanggung Jawab')
                        ->options(fn () => Cache::remember('user_options', 300, fn () => User::orderBy('name')->pluck('name', 'id')))
                        ->default(fn () => auth()->id())
                        ->searchable()
                        ->required(),
                    FileUpload::make('audit_document_path')
                        ->label('Bukti Perbaikan / Dokumen Audit')
                        ->directory('asset-audit')
                        ->preserveFilenames()
                        ->acceptedFileTypes(['application/pdf', 'image/*'])
                        ->maxSize(4096)
                        ->helperText('Upload bukti servis, BAP audit, foto, atau dokumen pendukung.')
                        ->columnSpanFull(),
                    FileUpload::make('nbh_document_path')
                        ->label('Dokumen NBH / Bukti Penutupan')
                        ->directory('asset-nbh')
                        ->preserveFilenames()
                        ->acceptedFileTypes(['application/pdf', 'image/*'])
                        ->maxSize(4096)
                        ->columnSpanFull(),
                    Textarea::make('nbh_notes')
                        ->label('Catatan Penyelesaian')
                        ->rows(3)
                        ->maxLength(65535)
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)
                    ->schema([
                        // Kolom kiri
                        Card::make()
                            ->schema([
                                // Dropdown untuk memilih kategori
                                Select::make('category_id')
                                    ->label(__('Kategori'))
                                    ->options(self::getCategoryOptions())
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $set('attributes', []);
                                            $categoryId = is_array($state) ? $state : [$state];
                                            $attributes = CustomAssetAttribute::where('is_active', true)
                                                ->where(function ($query) use ($categoryId) {
                                                    foreach ($categoryId as $id) {
                                                        $query->orWhereJsonContains('category_id', (int) $id); // Pastikan integer
                                                    }
                                                    $query->orWhereJsonLength('category_id', 0);
                                                })
                                                ->get()
                                                ->map(function ($attribute) {
                                                    return [
                                                        'custom_attribute_id' => $attribute->id,
                                                        'attribute_value' => '',
                                                        'document_expires_at' => null,
                                                        'document_number' => null,
                                                        'document_file_path' => null,
                                                        'document_notes' => null,
                                                    ];
                                                })
                                                ->toArray();
                                            $set('attributes', $attributes);
                                        }
                                    }),

                                Select::make('brand_id')
                                    ->translateLabel()
                                    ->options(fn () => Cache::remember('brand_options', 300, fn () => Brand::orderBy('name')->pluck('name', 'id')))
                                    ->searchable()
                                    ->required()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->required(),
                                    ])
                                    ->createOptionUsing(function ($data) {
                                        $brand = Brand::create([
                                            'name' => $data['name'],
                                        ]);
                                        Cache::forget('brand_options');

                                        return $brand->id;
                                    }),

                                // Input untuk nama
                                TextInput::make('name')
                                    ->label(__('Nama'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                TextInput::make('type')
                                    ->label('Model / Tipe Barang')
                                    ->placeholder('Contoh: ThinkPad T14, iPhone 15, Rak Gudang A')
                                    ->helperText('Isi model, varian, atau tipe barang dari vendor. Ini berbeda dari kategori aset.')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                            ])
                            ->columns(2),

                        Repeater::make('attributes')
                            ->relationship('attributes')
                            ->schema([
                                // Dropdown untuk memilih atribut
                                Select::make('custom_attribute_id')
                                    ->label(__('Atribut'))
                                    ->options(function (callable $get) {
                                        $categoryId = $get('../../category_id');
                                        $selectedId = $get('../custom_attribute_id');
                                        if ($categoryId) {
                                            $categoryId = is_array($categoryId) ? $categoryId : [$categoryId];
                                            // Ambil atribut yang sudah dipilih di semua entri repeater
                                            $selectedAttributes = collect($get('../attributes'))
                                                ->pluck('custom_attribute_id')
                                                ->filter()
                                                ->toArray();
                                            // Filter atribut yang sesuai dengan kategori dan belum dipilih
                                            $attributes = CustomAssetAttribute::where('is_active', true)
                                                ->where(function ($query) use ($categoryId) {
                                                    foreach ($categoryId as $id) {
                                                        $query->orWhereJsonContains('category_id', (int) $id);
                                                    }
                                                    $query->orWhereJsonLength('category_id', 0);
                                                })
                                                ->whereNotIn('id', $selectedAttributes) // Pastikan atribut yang sudah dipilih tidak muncul lagi
                                                ->pluck('name', 'id')
                                                ->toArray();

                                            return $attributes;
                                        }

                                        return [];
                                    })
                                    ->reactive()
                                    ->searchable()
                                    ->required()
                                    ->afterStateUpdated(function (callable $set) {
                                        $set('attribute_value', null);
                                        $set('document_expires_at', null);
                                        $set('document_number', null);
                                        $set('document_file_path', null);
                                        $set('document_notes', null);
                                    })
                                    ->afterStateHydrated(function ($state, callable $set) {
                                        if ($state) {
                                            // Ambil nama atribut berdasarkan ID
                                            $customAttribute = CustomAssetAttribute::find($state);

                                            if ($customAttribute) {
                                                // Tetapkan ID untuk backend dan nama untuk dropdown
                                                $set('custom_attribute_id', $customAttribute->id);
                                                $set('custom_attribute_label', $customAttribute->name);
                                            }
                                        }
                                    }),

                                // Input untuk nilai atribut
                                TextInput::make('attribute_value')
                                    ->label(__('Nilai Atribut'))
                                    ->required(fn (callable $get) => self::customAttributeIsRequired($get('custom_attribute_id')))
                                    ->reactive()
                                    ->visible(fn (callable $get) => self::attributeUsesType($get('custom_attribute_id'), CustomAssetAttribute::TYPE_TEXT))
                                    ->afterStateHydrated(function ($state, callable $set) {
                                        $set('attribute_value', $state ?? '');
                                    }),

                                // Input numerik
                                TextInput::make('attribute_value')
                                    ->label(__('Nilai Atribut'))
                                    ->required(fn (callable $get) => self::customAttributeIsRequired($get('custom_attribute_id')))
                                    ->numeric()
                                    ->reactive()
                                    ->visible(fn (callable $get) => self::attributeUsesType($get('custom_attribute_id'), CustomAssetAttribute::TYPE_NUMBER))
                                    ->afterStateHydrated(function ($state, callable $set) {
                                        $set('attribute_value', $state ?? '');
                                    }),

                                // Input untuk textarea
                                Textarea::make('attribute_value')
                                    ->label(__('Nilai Atribut'))
                                    ->required(fn (callable $get) => self::customAttributeIsRequired($get('custom_attribute_id')))
                                    ->reactive()
                                    ->visible(fn (callable $get) => self::attributeUsesType($get('custom_attribute_id'), CustomAssetAttribute::TYPE_TEXTAREA))
                                    ->afterStateHydrated(function ($state, callable $set) {
                                        $set('attribute_value', $state ?? '');
                                    }),

                                // Input untuk date picker
                                DatePicker::make('attribute_value')
                                    ->label(__('Nilai Atribut'))
                                    ->required(fn (callable $get) => self::customAttributeIsRequired($get('custom_attribute_id')))
                                    ->reactive()
                                    ->visible(fn (callable $get) => self::attributeUsesType($get('custom_attribute_id'), CustomAssetAttribute::TYPE_DATE))
                                    ->afterStateHydrated(function ($state, callable $set) {
                                        $set('attribute_value', $state ?? '');
                                    }),

                                TextInput::make('document_number')
                                    ->label('Nomor Dokumen')
                                    ->maxLength(255)
                                    ->placeholder('Contoh: STNK-001')
                                    ->visible(fn (callable $get) => self::attributeUsesType($get('custom_attribute_id'), CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY)),

                                DatePicker::make('document_expires_at')
                                    ->label('Berlaku Sampai')
                                    ->required(fn (callable $get) => self::attributeUsesType($get('custom_attribute_id'), CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY))
                                    ->reactive()
                                    ->visible(fn (callable $get) => self::attributeUsesType($get('custom_attribute_id'), CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY)),

                                FileUpload::make('document_file_path')
                                    ->label('Lampiran Dokumen')
                                    ->directory('asset-documents')
                                    ->preserveFilenames()
                                    ->maxSize(4096)
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->helperText('Upload PDF/JPG/PNG sebagai bukti pembaruan dokumen.')
                                    ->required(fn (callable $get) => self::attributeUsesType($get('custom_attribute_id'), CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY))
                                    ->columnSpan(2)
                                    ->visible(fn (callable $get) => self::attributeUsesType($get('custom_attribute_id'), CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY)),

                                Textarea::make('document_notes')
                                    ->label('Catatan Dokumen')
                                    ->rows(2)
                                    ->placeholder('Catatan opsional terkait pembaruan dokumen.')
                                    ->columnSpan(2)
                                    ->visible(fn (callable $get) => self::attributeUsesType($get('custom_attribute_id'), CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY)),

                                Placeholder::make('document_status')
                                    ->label('Status Pengingat')
                                    ->content(fn (callable $get): string => self::documentStatusFromForm($get))
                                    ->columnSpan(2)
                                    ->visible(fn (callable $get) => self::attributeUsesType($get('custom_attribute_id'), CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY)),
                            ])
                            ->columns(2)
                            ->columnSpan(2)
                            ->visible(fn (callable $get) => $get('category_id') !== null)
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => self::prepareAttributeRelationshipData($data))
                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => self::prepareAttributeRelationshipData($data))
                            ->afterStateHydrated(function ($state, callable $set, $record) {
                                if ($record && $record->attributes) {
                                    $state = [];
                                    foreach ($record->attributes as $attribute) {
                                        $state[] = self::attributeFormState($attribute);
                                    }
                                    $set('attributes', $state);
                                }
                            }),

                        Card::make()
                            ->schema([
                                Placeholder::make('lifecycle_hint')
                                    ->label('Panduan')
                                    ->content('Kelola kondisi fisik aset, dokumen NBH saat insiden, dan audit penjualan saat aset dijual.')
                                    ->columnSpanFull()
                                    ->extraAttributes([
                                        'class' => 'text-sm text-gray-500',
                                    ]),
                                Select::make('condition_status')
                                    ->label('Status Kondisi')
                                    ->options(AssetCondition::options())
                                    ->default(AssetCondition::Available->value)
                                    ->required()
                                    ->reactive()
                                    ->helperText('Pilih “Dijual” untuk aset yang sudah keluar inventaris, lalu lengkapi audit penjualannya.')
                                    ->columnSpan(1)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        if (in_array($state, AssetCondition::incidentValues(), true)) {
                                            if (! $get('nbh_status') || $get('nbh_status') === NbhStatus::None->value) {
                                                $set('nbh_status', NbhStatus::Pending->value);
                                            }
                                        } else {
                                            $set('nbh_status', NbhStatus::None->value);
                                            $set('nbh_responsible_user_id', null);
                                            $set('nbh_reported_at', null);
                                        }

                                        if ($state === AssetCondition::Sold->value) {
                                            $set('recipient_id', null);
                                            $set('recipient_business_entity_id', null);
                                        } else {
                                            $set('sold_at', null);
                                            $set('sold_to', null);
                                            $set('sold_price', null);
                                            $set('sale_document_path', null);
                                            $set('sale_notes', null);
                                        }
                                    }),
                                Select::make('nbh_status')
                                    ->label('Status NBH')
                                    ->options(function (callable $get): array {
                                        $condition = $get('condition_status');

                                        if (in_array($condition, AssetCondition::incidentValues(), true)) {
                                            return collect(NbhStatus::cases())
                                                ->reject(fn (NbhStatus $status) => $status === NbhStatus::None)
                                                ->mapWithKeys(fn (NbhStatus $status) => [$status->value => $status->label()])
                                                ->toArray();
                                        }

                                        return NbhStatus::options();
                                    })
                                    ->reactive()
                                    ->helperText('Perbarui saat proses penggantian selesai.')
                                    ->columnSpan(1)
                                    ->visible(fn (callable $get) => in_array($get('condition_status'), AssetCondition::incidentValues(), true) || $get('nbh_status') !== NbhStatus::None->value),
                                DatePicker::make('nbh_reported_at')
                                    ->label('Tanggal Insiden')
                                    ->helperText('Tanggal ditemukannya aset hilang atau rusak.')
                                    ->columnSpan(1)
                                    ->visible(fn (callable $get) => in_array($get('condition_status'), AssetCondition::incidentValues(), true) || $get('nbh_status') !== NbhStatus::None->value),
                                Select::make('nbh_responsible_user_id')
                                    ->label('Penanggung Jawab')
                                    ->options(fn () => Cache::remember('user_options', 300, fn () => User::orderBy('name')->pluck('name', 'id')))
                                    ->searchable()
                                    ->helperText('Pihak yang bertanggung jawab atas NBH.')
                                    ->columnSpan(1)
                                    ->required(fn (callable $get) => $get('nbh_status') === NbhStatus::Resolved->value)
                                    ->visible(fn (callable $get) => in_array($get('condition_status'), AssetCondition::incidentValues(), true) || $get('nbh_status') === NbhStatus::Resolved->value),
                                FileUpload::make('audit_document_path')
                                    ->label('Dokumen Audit')
                                    ->directory('asset-audit')
                                    ->preserveFilenames()
                                    ->maxSize(4096)
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->helperText('Unggah berita acara atau bukti audit (PDF/JPG, maks 4 MB). Wajib saat NBH selesai.')
                                    ->columnSpan(2)
                                    ->required(fn (callable $get) => $get('nbh_status') === NbhStatus::Resolved->value)
                                    ->visible(fn (callable $get) => in_array($get('condition_status'), AssetCondition::incidentValues(), true)),
                                FileUpload::make('nbh_document_path')
                                    ->label('Nota Barang Hilang (NBH)')
                                    ->directory('asset-nbh')
                                    ->preserveFilenames()
                                    ->maxSize(4096)
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->helperText('Unggah bukti penggantian atau nota NBH selesai.')
                                    ->columnSpan(2)
                                    ->required(fn (callable $get) => $get('nbh_status') === NbhStatus::Resolved->value)
                                    ->visible(fn (callable $get) => $get('nbh_status') === NbhStatus::Resolved->value),
                                Textarea::make('nbh_notes')
                                    ->label('Catatan NBH')
                                    ->placeholder('Masukkan kronologi singkat, hasil audit, atau tindak lanjut.')
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->visible(fn (callable $get) => in_array($get('condition_status'), AssetCondition::incidentValues(), true) || $get('nbh_status') !== NbhStatus::None->value),
                                DatePicker::make('sold_at')
                                    ->label('Tanggal Jual')
                                    ->native(false)
                                    ->helperText('Tanggal efektif aset dijual.')
                                    ->required(fn (callable $get) => $get('condition_status') === AssetCondition::Sold->value)
                                    ->visible(fn (callable $get): bool => self::shouldShowSaleAuditFields($get)),
                                TextInput::make('sold_to')
                                    ->label('Dijual Ke')
                                    ->maxLength(255)
                                    ->placeholder('Nama pembeli, vendor, atau tujuan penjualan')
                                    ->helperText('Isi pihak tujuan penjualan untuk kebutuhan audit.')
                                    ->required(fn (callable $get) => $get('condition_status') === AssetCondition::Sold->value)
                                    ->visible(fn (callable $get): bool => self::shouldShowSaleAuditFields($get)),
                                TextInput::make('sold_price')
                                    ->label('Harga Jual')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->helperText('Nilai transaksi penjualan aset.')
                                    ->required(fn (callable $get) => $get('condition_status') === AssetCondition::Sold->value)
                                    ->visible(fn (callable $get): bool => self::shouldShowSaleAuditFields($get)),
                                FileUpload::make('sale_document_path')
                                    ->label('Dokumen Penjualan')
                                    ->directory('asset-sales')
                                    ->preserveFilenames()
                                    ->maxSize(4096)
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->helperText('Unggah kuitansi, invoice, BA penjualan, atau bukti transfer (PDF/JPG, maks 4 MB).')
                                    ->required(fn (callable $get) => $get('condition_status') === AssetCondition::Sold->value)
                                    ->columnSpan(2)
                                    ->visible(fn (callable $get): bool => self::shouldShowSaleAuditFields($get)),
                                Textarea::make('sale_notes')
                                    ->label('Catatan Penjualan')
                                    ->placeholder('Nomor referensi, alasan penjualan, metode pembayaran, atau detail audit lainnya.')
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->visible(fn (callable $get): bool => self::shouldShowSaleAuditFields($get)),
                            ])
                            ->columns(3)
                            ->visible(fn () => auth()->user()?->hasAnyRole(['super_admin', 'general_affair']) ?? false),

                        Card::make()
                            ->schema([
                                Placeholder::make('recipient_hint')
                                    ->label('Pengaturan Penerima')
                                    ->content('Opsional: sesuaikan penerima aset secara manual untuk kasus khusus.')
                                    ->columnSpanFull()
                                    ->extraAttributes([
                                        'class' => 'text-sm text-gray-500',
                                    ]),
                                Select::make('recipient_business_entity_id')
                                    ->translateLabel()
                                    ->options(fn () => Cache::remember('business_entity_options', 300, fn () => BusinessEntity::orderBy('name')->pluck('name', 'id')))
                                    ->searchable()
                                    ->helperText('Kosongkan jika tetap mengikuti data transfer terakhir.'),
                                Select::make('recipient_id')
                                    ->translateLabel()
                                    ->options(fn () => Cache::remember('user_options', 300, fn () => User::orderBy('name')->pluck('name', 'id')))
                                    ->searchable()
                                    ->helperText('Pilih pemegang aset saat ini.'),
                            ])
                            ->columns(2)
                            ->visible(fn () => auth()->user()?->hasRole('super_admin')),
                    ])
                    ->columnSpan(2),

                // Kolom kanan
                Card::make()
                    ->schema([
                        DatePicker::make('purchase_date')
                            ->translateLabel()
                            ->required(),
                        Select::make('business_entity_id')
                            ->translateLabel()
                            ->options(fn () => Cache::remember('business_entity_options', 300, fn () => BusinessEntity::orderBy('name')->pluck('name', 'id')))
                            ->searchable()
                            ->required(),
                        TextInput::make('item_price')
                            ->translateLabel()
                            ->numeric(),
                        TextInput::make('qty')
                            ->translateLabel()
                            ->default(1)
                            ->required()
                            ->numeric(),
                        Select::make('asset_location_id')
                            ->translateLabel()
                            ->options(fn () => Cache::remember('asset_location_options', 300, fn () => AssetLocation::orderBy('name')->pluck('name', 'id')))
                            ->searchable()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->translateLabel()
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('address')
                                    ->translateLabel()
                                    ->maxLength(255),
                                TextInput::make('description')
                                    ->translateLabel()
                                    ->maxLength(255),
                            ])
                            ->createOptionUsing(function ($data) {
                                $assetLocation = AssetLocation::create([
                                    'name' => $data['name'],
                                    'address' => $data['address'],
                                    'description' => $data['description'],
                                ]);
                                Cache::forget('asset_location_options');

                                return $assetLocation->id;
                            }),
                        FileUpload::make('image')
                            ->label('Foto Aset')
                            ->directory('assets') // Define the directory to store images
                            ->image() // Only allow image uploads
                            ->maxSize(2048)
                            ->resize(50),
                    ])
                    ->columns(1)
                    ->columnSpan(1),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('purchase_date')->translateLabel()->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('businessEntity.name') // Mengambil nama dari relasi businessEntity
                    ->translateLabel()
                    ->badge()
                    ->color(fn ($record) => $record->businessEntity->color)
                    ->getStateUsing(fn ($record) => $record->businessEntity->name)
                    ->toggleable(),
                TextColumn::make('name')->translateLabel()->sortable()->searchable()->toggleable(),
                TextColumn::make('category.name')->translateLabel()->sortable()->toggleable(),
                TextColumn::make('brand.name')->translateLabel()->sortable()->searchable()->toggleable(),
                TextColumn::make('type')->label('Model / Tipe')->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('serial_number')->translateLabel()->sortable()->searchable()->toggleable(),
                TextColumn::make('imei1')->translateLabel()->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('imei2')->translateLabel()->sortable()->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('item_price')->translateLabel()->sortable()->money('IDR', true)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('item_age')
                    ->translateLabel()
                    ->sortable(query: fn ($query, $direction) => $query->sortByItemAge($direction))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('qty') // Mengambil nama dari relasi businessEntity
                    ->translateLabel()
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('assetLocation.name')->translateLabel()->sortable()->searchable()->toggleable(),
                TextColumn::make('condition_status_label')
                    ->label('Status Aset')
                    ->badge()
                    ->color(fn ($state, Asset $record): string => $record->condition_status_color ?? 'secondary')
                    ->toggleable(),
                TextColumn::make('sold_at')
                    ->label('Tanggal Jual')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sold_to')
                    ->label('Dijual Ke')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sold_price')
                    ->label('Harga Jual')
                    ->money('IDR', true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nbh_status_label')
                    ->label('Status NBH')
                    ->badge()
                    ->color(fn ($state, Asset $record): string => $record->nbh_status_color ?? 'secondary')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('businessEntity')
                    ->relationship('businessEntity', 'name')
                    ->translateLabel()
                    ->multiple()
                    ->searchable()
                    ->preload(),
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->label('Kategori')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                SelectFilter::make('condition_status')
                    ->label('Status Aset')
                    ->options(AssetCondition::options())
                    ->multiple(),
                SelectFilter::make('nbh_status')
                    ->label('Status NBH')
                    ->options(NbhStatus::options())
                    ->multiple(),
                SelectFilter::make('assetLocation')
                    ->relationship('assetLocation', 'name')
                    ->translateLabel()
                    ->multiple()
                    ->searchable()
                    ->preload(),
                Filter::make('table_data_filter')
                    ->label('Filter Data Tabel')
                    ->form([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('serial_number')
                                    ->label('Serial Number')
                                    ->placeholder('Cari serial number...'),
                                TextInput::make('imei')
                                    ->label('IMEI')
                                    ->placeholder('Cari IMEI 1 / IMEI 2...'),
                                TextInput::make('item_price_min')
                                    ->label('Harga Minimal')
                                    ->numeric(),
                                TextInput::make('item_price_max')
                                    ->label('Harga Maksimal')
                                    ->numeric(),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(filled($data['serial_number'] ?? null), fn (Builder $q) => $q->where('serial_number', 'like', '%'.trim($data['serial_number']).'%'))
                            ->when(filled($data['imei'] ?? null), function (Builder $q) use ($data) {
                                $imei = trim($data['imei']);

                                $q->where(function (Builder $imeiQuery) use ($imei) {
                                    $imeiQuery
                                        ->where('imei1', 'like', '%'.$imei.'%')
                                        ->orWhere('imei2', 'like', '%'.$imei.'%');
                                });
                            })
                            ->when(filled($data['item_price_min'] ?? null), fn (Builder $q) => $q->where('item_price', '>=', (float) $data['item_price_min']))
                            ->when(filled($data['item_price_max'] ?? null), fn (Builder $q) => $q->where('item_price', '<=', (float) $data['item_price_max']));
                    }),
            ])
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columnToggleFormColumns(2)
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormWidth('4xl')
            ->filtersTriggerAction(fn (Action $action) => $action
                ->label('Filter Audit')
                ->slideOver())
            ->actions([
                Action::make('completeRepair')
                    ->label('Selesaikan Perbaikan')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Asset $record): bool => (auth()->user()?->hasAnyRole(['super_admin', 'general_affair']) ?? false)
                        && $record->condition_status === AssetCondition::Damaged
                        && $record->nbh_status === NbhStatus::Pending)
                    ->form(self::repairCompletionFormSchema())
                    ->slideOver()
                    ->modalWidth('md')
                    ->action(function (Asset $record, array $data): void {
                        $record->completeRepairProcessing(auth()->user(), $data);

                        Notification::make()
                            ->title('Perbaikan selesai')
                            ->body("Aset \"{$record->name}\" sudah kembali operasional dan NBH ditutup.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
                BulkAction::make('pindahkanKeAtribut')
                    ->label('Pindahkan ke Atribut')
                    ->action(fn (Collection $records) => self::pindahkanKeAssetAttributeBulk($records))
                    ->requiresConfirmation()
                    ->color('primary')
                    ->icon('heroicon-o-arrow-right'), // Ikon untuk bulk action
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AssetTransfersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'edit' => Pages\EditAsset::route('/{record}/edit'),
            'view' => Pages\ViewAsset::route('/{record}'),
        ];
    }

    public static function getModelLabel(): string
    {
        return __('Asset');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Assets');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                ComponentsGrid::make(1)
                    ->schema([
                        ComponentsSection::make('Informasi Aset')
                            ->schema([
                                ComponentsGrid::make(2)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label(__('Nama Aset'))
                                            ->columnSpan(2)
                                            ->extraAttributes([
                                                'style' => 'font-weight:bold; font-size:1.2em; color:#333;',
                                            ]),
                                        TextEntry::make('category.name')
                                            ->label(__('Kategori')),
                                        TextEntry::make('brand.name')
                                            ->label(__('Merek')),
                                        TextEntry::make('type')
                                            ->label('Model / Tipe'),
                                        ImageEntry::make('image')
                                            ->label('Foto Aset')
                                            ->width('100px')
                                            ->height('100px'),
                                    ]),
                            ])
                            ->grow(true),

                        ComponentsSection::make('Atribut Khusus')
                            ->schema([
                                ComponentsGrid::make(2)
                                    ->schema(function ($record) {
                                        $record->load('attributes.customAttribute');

                                        return $record->attributes->map(function ($attribute) {
                                            $entry = TextEntry::make("custom_attribute_{$attribute->custom_attribute_id}")
                                                ->label($attribute->customAttribute?->name ?? 'Unknown Attribute')
                                                ->state($attribute->displayValue());

                                            if ($attribute->isDocumentExpiryAttribute()) {
                                                $entry
                                                    ->badge()
                                                    ->color(fn () => $attribute->expiryReminderStatusColorOn());

                                                if ($attribute->documentUrl()) {
                                                    $entry
                                                        ->url($attribute->documentUrl(), true)
                                                        ->openUrlInNewTab();
                                                }
                                            }

                                            return $entry;
                                        })->toArray();
                                    }),
                            ]),

                        ComponentsSection::make('Detail Pembelian')
                            ->schema([
                                ComponentsGrid::make(4)
                                    ->schema([
                                        TextEntry::make('purchase_date')
                                            ->label(__('Tanggal Pembelian'))
                                            ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('d/m/Y'))
                                            ->extraAttributes(['style' => 'color:#007BFF;']),
                                        TextEntry::make('item_price')
                                            ->label(__('Harga'))
                                            ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 0, ',', '.'))
                                            ->extraAttributes([
                                                'style' => 'color:#28a745; font-weight:bold;',
                                            ]),
                                        TextEntry::make('qty')
                                            ->label(__('Kuantitas')),
                                        TextEntry::make('businessEntity.name')
                                            ->label(__('Entitas Bisnis')),
                                    ]),
                            ]),
                    ])
                    ->columnSpan([
                        'default' => 'full',
                        'lg' => 2,
                    ]),

                ComponentsGrid::make(1)
                    ->schema([
                        ComponentsSection::make('Status & NBH')
                            ->schema([
                                ComponentsGrid::make(1)
                                    ->schema([
                                        TextEntry::make('condition_status_label')
                                            ->label(__('Status Aset'))
                                            ->badge()
                                            ->color(fn ($state, Asset $record): string => $record->condition_status_color ?? 'secondary')
                                            ->extraAttributes(['style' => 'font-weight:bold;']),
                                        TextEntry::make('nbh_status_label')
                                            ->label(__('Status NBH'))
                                            ->badge()
                                            ->color(fn ($state, Asset $record): string => $record->nbh_status_color ?? 'secondary'),
                                        TextEntry::make('validasi_status')
                                            ->label(__('Status Validasi'))
                                            ->badge()
                                            ->color(fn ($state): string => $state === 'Valid' ? 'success' : 'danger')
                                            ->state(fn (Asset $record): string => $record->checkValidRecipient() ? 'Valid' : 'Tidak Valid'),
                                    ]),
                                ComponentsGrid::make(1)
                                    ->schema([
                                        TextEntry::make('asset_location_display')
                                            ->label(__('Lokasi Aset'))
                                            ->state(fn (Asset $record): string => $record->assetLocation?->name ?? '-'),
                                        TextEntry::make('recipient_display')
                                            ->label(__('Pemegang Aset'))
                                            ->state(fn (Asset $record): string => $record->recipient?->name ?? '-'),
                                    ]),
                                ComponentsGrid::make(1)
                                    ->schema([
                                        TextEntry::make('nbh_reported_at_display')
                                            ->label(__('Tanggal Insiden'))
                                            ->state(fn (Asset $record): string => $record->nbh_status instanceof NbhStatus && $record->nbh_status !== NbhStatus::None
                                                ? optional($record->nbh_reported_at)?->format('d M Y') ?? '-'
                                                : '-'),
                                        TextEntry::make('nbh_responsible_display')
                                            ->label(__('Penanggung Jawab'))
                                            ->state(fn (Asset $record): string => $record->nbh_status instanceof NbhStatus && $record->nbh_status !== NbhStatus::None
                                                ? $record->nbhResponsible?->name ?? '-'
                                                : '-'),
                                    ])
                                    ->visible(fn (Asset $record): bool => $record->nbh_status instanceof NbhStatus && $record->nbh_status !== NbhStatus::None),
                                TextEntry::make('nbh_notes')
                                    ->label(__('Catatan NBH'))
                                    ->columnSpanFull()
                                    ->visible(fn (Asset $record): bool => filled($record->nbh_notes)),
                            ]),

                        ComponentsSection::make('Audit Penjualan')
                            ->schema([
                                ComponentsGrid::make(3)
                                    ->schema([
                                        TextEntry::make('sold_at')
                                            ->label(__('Tanggal Jual'))
                                            ->state(fn (Asset $record): string => optional($record->sold_at)?->format('d M Y') ?? '-'),
                                        TextEntry::make('sold_to')
                                            ->label(__('Dijual Ke'))
                                            ->state(fn (Asset $record): string => $record->sold_to ?? '-'),
                                        TextEntry::make('sold_price')
                                            ->label(__('Harga Jual'))
                                            ->state(fn (Asset $record): string => $record->sold_price !== null ? 'Rp '.number_format($record->sold_price, 0, ',', '.') : '-'),
                                    ]),
                                TextEntry::make('sale_notes')
                                    ->label(__('Catatan Penjualan'))
                                    ->columnSpanFull()
                                    ->visible(fn (Asset $record): bool => filled($record->sale_notes)),
                            ])
                            ->visible(fn (Asset $record): bool => self::hasSaleAuditRecord($record)),

                        ComponentsSection::make('Dokumen Pendukung')
                            ->schema([
                                ComponentsGrid::make(2)
                                    ->schema([
                                        TextEntry::make('audit_document_path')
                                            ->label(__('Dokumen Audit'))
                                            ->url(fn (Asset $record) => $record->audit_document_path ? Storage::url($record->audit_document_path) : null, true)
                                            ->openUrlInNewTab()
                                            ->visible(fn (Asset $record): bool => filled($record->audit_document_path)),
                                        TextEntry::make('nbh_document_path')
                                            ->label(__('Nota Barang Hilang'))
                                            ->url(fn (Asset $record) => $record->nbh_document_path ? Storage::url($record->nbh_document_path) : null, true)
                                            ->openUrlInNewTab()
                                            ->visible(fn (Asset $record): bool => filled($record->nbh_document_path)),
                                        TextEntry::make('sale_document_path')
                                            ->label(__('Dokumen Penjualan'))
                                            ->url(fn (Asset $record) => self::resolveDocumentUrl($record->sale_document_path), true)
                                            ->openUrlInNewTab()
                                            ->visible(fn (Asset $record): bool => filled($record->sale_document_path)),
                                    ]),
                            ])
                            ->visible(fn (Asset $record): bool => filled($record->audit_document_path) || filled($record->nbh_document_path) || filled($record->sale_document_path)),
                    ])
                    ->columnSpan([
                        'default' => 'full',
                        'lg' => 1,
                    ]),
            ])
            ->columns([
                'default' => 1,
                'lg' => 3,
            ]);
    }

    // In your AssetAttribute model
    protected static function pindahkanKeAssetAttributeBulk($records)
    {
        foreach ($records as $record) {
            // Daftar kolom yang akan dipindahkan sebagai `attribute_key` dan `attribute_value`
            $attributes = [
                '3' => $record->serial_number,
                '1' => $record->imei1,
                '2' => $record->imei2,
            ];

            foreach ($attributes as $key => $value) {
                // Pastikan hanya memindahkan jika $value tidak null atau kosong
                if (! is_null($value) && $value !== '') {
                    AssetAttribute::updateOrCreate(
                        [
                            'asset_id' => $record->id,
                            'custom_attribute_id' => $key,
                        ],
                        [
                            'attribute_value' => $value,
                        ]
                    );
                }
            }
        }

        Notification::make()
            ->title('Sukses')
            ->body('Atribut berhasil dipindahkan ke AssetAttribute untuk aset yang dipilih.')
            ->success()
            ->send();
    }
}
