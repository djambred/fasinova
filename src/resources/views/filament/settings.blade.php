<x-filament-panels::page>
    {{-- Jarak pakai style inline: kelas Tailwind di view ini tidak ikut terkompilasi ke CSS Filament. --}}
    <form wire:submit="save" style="display:flex;flex-direction:column;align-items:flex-start;gap:1.5rem">
        <div style="width:100%">{{ $this->form }}</div>
        <x-filament::button type="submit">Simpan</x-filament::button>
    </form>
</x-filament-panels::page>
