# Feature Implementation Guide: Colored Tags and Filtering

This guide provides the complete, step-by-step instructions to implement the feature: "The Users can create colored tags, apply them to papers, and filter the library by tag."

## Step 1: Create the Database Migrations and Model

We need a `tags` table to store the custom tags, a pivot table `paper_tag` to handle the Many-to-Many relationship, and a `Tag` model.

**1. Generate the files:**
Run the following commands in your terminal:

```bash
php artisan make:model Tag -m
php artisan make:migration create_paper_tag_table

```

**2. Update `database/migrations/xxxx_xx_xx_xxxxxx_create_tags_table.php`:**

```php
public function up(): void
{
    Schema::create('tags', function (Blueprint $table) {$table->id();
        $table->string('name');$table->string('color')->default('#4F46E5'); // Default Indigo color
        $table->foreignId('user_id')->constrained()->onDelete('cascade');$table->timestamps();
    });
}

```

**3. Update `database/migrations/xxxx_xx_xx_xxxxxx_create_paper_tag_table.php`:**

```php
public function up(): void
{
    Schema::create('paper_tag', function (Blueprint $table) {
        $table->id();$table->foreignId('paper_id')->constrained()->onDelete('cascade');
        $table->foreignId('tag_id')->constrained()->onDelete('cascade');$table->timestamps();
    });
}

```

Run `php artisan migrate` after saving both files.

---

## Step 2: Define Model Relationships

We need to set up the relationships between User, Tag, and Paper.

**1. Update `app/Models/Tag.php`:**

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $fillable = ['name', 'color', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function papers()
    {
        return $this->belongsToMany(Paper::class);
    }
}

```

**2. Update `app/Models/Paper.php`:**
Add this method at the bottom of the class:

```php
    // Relationship: A paper can have many tags
    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

```

---

## Step 3: Create TagController and Update Routes

We need a controller to handle creating new tags.

**1. Create TagController:**

```bash
php artisan make:controller TagController

```

**2. Update `app/Http/Controllers/TagController.php`:**

```php
namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TagController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:7', // Hex color code
        ]);

        Tag::create([
            'name' => $request->name,
            'color' => $request->color,
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Tag created successfully!');
    }
}

```

**3. Update `routes/web.php`:**
At the top, import the controller:

```php
use App\Http\Controllers\TagController;

```

Inside your `auth` middleware group, add:

```php
Route::post('/tags', [TagController::class, 'store'])->name('tags.store');

```

---

## Step 4: Update PaperController (Filtering and Syncing)

We need to modify the `PaperController` to handle tag filtering on the index page and syncing tags on the edit page.

**1. Open `app/Http/Controllers/PaperController.php` and import the Tag model at the top:**

```php
use App\Models\Tag;

```

**2. Update the `index` method:**
Replace the entire `index` method to support tag filtering:

```php
    public function index(Request $request)
    {
        $query = Paper::where('user_id', Auth::id())->latest();

        // Apply Tag Filter if requested
        if ($request->has('tag') && $request->tag != '') {$query->whereHas('tags', function($q) use ($request) {
                $q->where('tags.id',$request->tag);
            });
        }

        $papers = $query->get();$tags = Tag::where('user_id', Auth::id())->get(); // Fetch tags for the dropdown

        return view('papers.index', compact('papers', 'tags'));
    }

```

**3. Update the `edit` method:**
Update it to fetch the user's tags and pass them to the view.

```php
    public function edit(Paper $paper)
    {
        if ($paper->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $collections = Collection::where('user_id', Auth::id())->get();$tags = Tag::where('user_id', Auth::id())->get(); // Fetch tags

        return view('papers.edit', compact('paper', 'collections', 'tags'));
    }

```

**4. Update the `update` method:**
Right below the collection sync block (`$paper->collections()->sync(...)`), add the logic to sync tags:

```php
        // Sync tags
        if ($request->has('tags')) {
            $paper->tags()->sync($request->tags);
        } else {
            $paper->tags()->sync([]);
        }

```

---

## Step 5: Update the Views

**1. Update `resources/views/papers/index.blade.php` (The Library):**
Add the filter dropdown right below the `+ Add Paper` button inside the header or above the table. Replace the current `<x-slot name="header">` block with this:

```html
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('My Library') }}
            </h2>

            <div class="flex space-x-4 items-center">
                <!-- Tag Filter Form -->
                <form method="GET" action="{{ route('papers.index') }}" class="flex items-center space-x-2">
                    <select name="tag" class="text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm" onchange="this.form.submit()">
                        <option value="">All Tags</option>
                        @foreach($tags as$tag)
                            <option value="{{ $tag->id }}" {{ request('tag') == $tag->id ? 'selected' : '' }}>
                                {{ $tag->name }}
                            </option>
                        @endforeach
                    </select>
                </form>

                <a href="{{ route('papers.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                    + Add Paper
                </a>
            </div>
        </div>
    </x-slot>

```

Also, inside `index.blade.php`, display the tags on the paper row. Find the column where you show the title, and put this exactly below the authors list:

```html
<div class="mt-1 flex flex-wrap gap-1">
  @foreach($paper->tags as$tag)
  <span
    class="px-2 py-0.5 text-[10px] font-medium text-white rounded-full"
    style="background-color: {{ $tag->color }}"
  >
    {{ $tag->name }}
  </span>
  @endforeach
</div>
```

**2. Update `resources/views/papers/edit.blade.php` (Edit Paper):**
Add the UI to apply tags and create new ones. Paste this directly below the `Collections` block and above the Submit button:

```html
<!-- Organize into Tags -->
<div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
  <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
    Apply Tags
  </h3>

  @if($tags->isEmpty())
  <p class="text-sm text-gray-500 dark:text-gray-400">
    You haven't created any tags yet.
  </p>
  @else
  <div class="flex flex-wrap gap-3 mt-2">
    @foreach($tags as$tag)
    <label
      class="inline-flex items-center border border-gray-200 dark:border-gray-600 px-3 py-1 rounded-full cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition"
    >
      <input
        type="checkbox"
        name="tags[]"
        value="{{ $tag->id }}"
        class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500"
        {{
        $paper-
      />tags->contains($tag->id) ? 'checked' : '' }}>
      <span
        class="ml-2 w-3 h-3 rounded-full inline-block"
        style="background-color: {{ $tag->color }};"
      ></span>
      <span class="ml-1 text-sm text-gray-700 dark:text-gray-300"
        >{{ $tag->name }}</span
      >
    </label>
    @endforeach
  </div>
  @endif
</div>
```

**3. Add a New View to Create Tags (Optional but recommended):**
To let users create new tags, you can add a simple form inside `papers/edit.blade.php` (just below the Apply Tags section but outside the main paper update form). However, HTML doesn't allow nested forms.

Instead, tell your friend to add a simple modal or a separate route for tag management if they want to get fancy. For a quick fix, they can add this small form at the very bottom of the `edit.blade.php` page, _outside_ and below the main `<form>` tag:

```html
<div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6">
  <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
    Create a New Tag
  </h3>
  <form
    method="POST"
    action="{{ route('tags.store') }}"
    class="flex items-center space-x-4"
  >
    @csrf
    <div>
      <x-text-input
        id="name"
        class="block w-full text-sm"
        type="text"
        name="name"
        placeholder="Tag Name"
        required
      />
    </div>
    <div>
      <input
        type="color"
        name="color"
        value="#4F46E5"
        class="h-10 w-10 border-0 rounded cursor-pointer"
        required
      />
    </div>
    <x-primary-button type="submit">Add Tag</x-primary-button>
  </form>
</div>
```

Save all the files, and the feature is completely ready to use!
