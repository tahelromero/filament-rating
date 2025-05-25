<?php

// Example 1: Custom Import with Header Validation
// ================================================

use EightyNine\ExcelImport\EnhancedDefaultImport;
use Illuminate\Support\Collection;

class CustomUserImport extends EnhancedDefaultImport
{
    protected function beforeCollection(Collection $collection): void
    {
        // Example 1: Validate required headers
        $requiredHeaders = ['name', 'email', 'phone'];
        $this->validateHeaders($requiredHeaders, $collection);

        // Example 2: Custom validation using form data
        $formData = $this->customImportData; // Access custom data from the form
        
        // Example: Check if a specific condition from the form is met
        if (isset($formData['department_id'])) {
            $departmentExists = \App\Models\Department::where('id', $formData['department_id'])->exists();
            $this->validateCustomCondition(
                $departmentExists,
                'The selected department does not exist. Please select a valid department.'
            );
        }

        // Example 3: File-level validation (e.g., maximum number of rows)
        if ($collection->count() > 1000) {
            $this->stopImportWithError('Import file contains too many rows. Maximum allowed is 1000 rows.');
        }

        // Example 4: Validate file content based on business logic
        $hasValidData = $collection->every(function ($row) {
            return !empty($row['name']) && !empty($row['email']);
        });

        if (!$hasValidData) {
            $this->stopImportWithError('All rows must have both name and email filled.');
        }
    }

    protected function beforeCreateRecord(array $data, $row): void
    {
        // Example: Row-level validation with custom logic
        if (isset($data['email'])) {
            $existingUser = \App\Models\User::where('email', $data['email'])->first();
            if ($existingUser) {
                $this->stopImportWithWarning(
                    "User with email {$data['email']} already exists. Import stopped to prevent duplicates."
                );
            }
        }

        // Example: Validate against custom business rules
        if (isset($data['age']) && $data['age'] < 18) {
            $this->stopImportWithError(
                'All users must be 18 years or older. Found user with age: ' . $data['age']
            );
        }
    }

    protected function afterCollection(Collection $collection): void
    {
        // Example: Show success message with statistics
        $count = $collection->count();
        $this->stopImportWithSuccess("Successfully imported {$count} users!");
    }
}

// Example 2: Using the Custom Import in a Filament Resource
// =========================================================

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \EightyNine\ExcelImport\ExcelImportAction::make()
                ->color("primary")
                ->use(CustomUserImport::class)
                
                // Add custom form fields for additional validation
                ->beforeUploadField([
                    Select::make('department_id')
                        ->label('Department')
                        ->options(\App\Models\Department::pluck('name', 'id'))
                        ->required()
                        ->helperText('Users will be assigned to this department'),
                        
                    TextInput::make('batch_name')
                        ->label('Batch Name')
                        ->required()
                        ->helperText('Name for this import batch for tracking'),
                ])
                
                // Pass the form data to the custom import
                ->beforeImport(function (array $data, $livewire, $excelImportAction) {
                    // Pass custom data from the form to the import class
                    $excelImportAction->customImportData([
                        'department_id' => $data['department_id'],
                        'batch_name' => $data['batch_name'],
                        'imported_by' => auth()->id(),
                        'import_timestamp' => now(),
                    ]);

                    // Add additional data that will be merged with each row
                    $excelImportAction->additionalData([
                        'department_id' => $data['department_id'],
                        'status' => 'active',
                        'imported_by' => auth()->id(),
                    ]);
                }),

            Actions\CreateAction::make(),
        ];
    }
}

// Example 3: Custom Relationship Import with Validation
// =====================================================

use EightyNine\ExcelImport\EnhancedDefaultRelationshipImport;

class CustomPostImport extends EnhancedDefaultRelationshipImport
{
    protected function beforeCollection(Collection $collection): void
    {
        // Validate headers for posts
        $requiredHeaders = ['title', 'content', 'category'];
        $this->validateHeaders($requiredHeaders, $collection);

        // Check if the user has permission to create posts in this category
        $categories = $collection->pluck('category')->unique();
        foreach ($categories as $category) {
            $categoryExists = \App\Models\Category::where('name', $category)->exists();
            $this->validateCustomCondition(
                $categoryExists,
                "Category '{$category}' does not exist. Please ensure all categories exist before importing."
            );
        }
    }

    protected function beforeCreateRecord(array $data, $row): void
    {
        // Validate that the post title is unique for this user
        $existingPost = $this->ownerRecord->posts()
            ->where('title', $data['title'])
            ->exists();
            
        if ($existingPost) {
            $this->stopImportWithError(
                "Post with title '{$data['title']}' already exists for this user."
            );
        }
    }
}

// Example 4: Advanced Validation with Multiple Conditions
// =======================================================

class AdvancedProductImport extends EnhancedDefaultImport
{
    protected function beforeCollection(Collection $collection): void
    {
        // Multiple validation checks
        $this->validateHeaders(['sku', 'name', 'price', 'category'], $collection);
        
        // Check for duplicate SKUs in the file itself
        $skus = $collection->pluck('sku');
        $duplicateSkus = $skus->duplicates();
        
        if ($duplicateSkus->isNotEmpty()) {
            $this->stopImportWithError(
                'Duplicate SKUs found in the file: ' . $duplicateSkus->implode(', ')
            );
        }

        // Validate all prices are numeric and positive
        $invalidPrices = $collection->filter(function ($row) {
            return !is_numeric($row['price']) || $row['price'] <= 0;
        });

        if ($invalidPrices->isNotEmpty()) {
            $this->stopImportWithError(
                'Invalid prices found. All prices must be positive numbers.'
            );
        }

        // Business logic validation using custom import data
        if (isset($this->customImportData['supplier_id'])) {
            $supplier = \App\Models\Supplier::find($this->customImportData['supplier_id']);
            if (!$supplier || !$supplier->is_active) {
                $this->stopImportWithError(
                    'The selected supplier is not active. Cannot import products.'
                );
            }
        }
    }

    protected function beforeCreateRecord(array $data, $row): void
    {
        // Check if SKU already exists in database
        if (\App\Models\Product::where('sku', $data['sku'])->exists()) {
            $this->stopImportWithWarning(
                "Product with SKU '{$data['sku']}' already exists. Skipping import to prevent conflicts."
            );
        }
    }
}

// Example 5: Using in Filament with Enhanced Error Handling
// =========================================================

protected function getHeaderActions(): array
{
    return [
        \EightyNine\ExcelImport\ExcelImportAction::make()
            ->color("primary")
            ->use(AdvancedProductImport::class)
            ->beforeUploadField([
                Select::make('supplier_id')
                    ->label('Supplier')
                    ->options(\App\Models\Supplier::where('is_active', true)->pluck('name', 'id'))
                    ->required(),
                    
                TextInput::make('import_notes')
                    ->label('Import Notes')
                    ->maxLength(500),
            ])
            ->beforeImport(function (array $data, $livewire, $excelImportAction) {
                $excelImportAction->customImportData([
                    'supplier_id' => $data['supplier_id'],
                    'import_notes' => $data['import_notes'],
                    'imported_by' => auth()->user()->name,
                ]);

                $excelImportAction->additionalData([
                    'supplier_id' => $data['supplier_id'],
                    'created_by' => auth()->id(),
                ]);
            })
            ->sampleExcel([
                ['sku' => 'PROD001', 'name' => 'Sample Product', 'price' => '29.99', 'category' => 'Electronics'],
                ['sku' => 'PROD002', 'name' => 'Another Product', 'price' => '49.99', 'category' => 'Books'],
            ]),
    ];
}