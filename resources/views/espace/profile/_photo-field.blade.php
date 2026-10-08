{{-- Photo input: opens the front camera on phones, gallery as fallback. --}}
<x-field id="photo" label="Photo de votre visage" :error="$errors->first('photo')" hint="De face, bien éclairée, sans lunettes de soleil. JPG ou PNG, 2 Mo maximum." required>
    <input
        id="photo"
        name="photo"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        capture="user"
        required
        data-validate
        aria-describedby="photo-hint photo-error"
        class="block w-full rounded-2xl bg-white text-base text-slate-700 ring-1 ring-inset ring-slate-300 file:mr-4 file:min-h-12 file:cursor-pointer file:border-0 file:bg-primary-50 file:px-4 file:font-semibold file:text-primary-700"
    >
</x-field>
