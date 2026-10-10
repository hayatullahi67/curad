@extends('layouts.app')

@section('content')
<div class="px-8 py-8">
    <!-- Header -->
    <header class="flex justify-between items-center mb-10">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">Community Broadcast</h1>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mt-1">Patient Outreach • Facility: {{ $pharmacy->name }}</p>
        </div>
        <div class="flex items-center space-x-4">
            <div class="px-5 py-2.5 bg-blue-50 border border-blue-100 rounded-xl">
                <p class="text-[8px] font-black text-blue-400 uppercase tracking-widest leading-none mb-1">Network Reach</p>
                <p class="text-xs font-black text-blue-600">{{ number_format($followerCount) }} Followers</p>
            </div>
            <button type="button" data-cura-modal-open="broadcast-modal" class="px-5 py-2.5 bg-slate-900 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg active:scale-95 transition-all">
                <i class="fas fa-bullhorn mr-2"></i> New Broadcast
            </button>
        </div>
    </header>

    <!-- Broadcast History -->
    <div class="max-w-5xl mx-auto">
        <div class="mb-8">
            <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest mb-6">Transmission History</h4>
        </div>

        <div class="space-y-6">
            @forelse($broadcasts as $broadcast)
            <div class="bg-white p-8 rounded-[2.5rem] border border-slate-200 shadow-sm hover:shadow-md transition-all group relative overflow-hidden">
                <!-- Type Indicator -->
                <div class="absolute left-0 top-0 bottom-0 w-2 {{ $broadcast->type === 'emergency' ? 'bg-red-600' : ($broadcast->type === 'stock' ? 'bg-blue-600' : 'bg-emerald-600') }}"></div>
                
                <div class="flex items-start justify-between mb-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-14 h-14 rounded-2xl flex items-center justify-center {{ $broadcast->type === 'emergency' ? 'bg-red-50 text-red-600' : ($broadcast->type === 'stock' ? 'bg-blue-50 text-blue-600' : 'bg-emerald-50 text-emerald-600') }}">
                            @if($broadcast->type === 'emergency')
                                <i class="fas fa-exclamation-triangle text-xl"></i>
                            @elseif($broadcast->type === 'stock')
                                <i class="fas fa-box-open text-xl"></i>
                            @else
                                <i class="fas fa-bullhorn text-xl"></i>
                            @endif
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-slate-900 tracking-tight mb-1">{{ $broadcast->title }}</h3>
                            <div class="flex items-center space-x-4">
                                <span class="px-3 py-1 {{ $broadcast->type === 'emergency' ? 'bg-red-50 text-red-600 border-red-100' : ($broadcast->type === 'stock' ? 'bg-blue-50 text-blue-600 border-blue-100' : 'bg-emerald-50 text-emerald-600 border-emerald-100') }} border rounded-lg text-[8px] font-black uppercase tracking-widest">
                                    {{ ucfirst($broadcast->type) }}
                                </span>
                                <span class="text-[9px] font-bold text-slate-400 uppercase">{{ $broadcast->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Recipients</p>
                        <p class="text-2xl font-black text-slate-900 tracking-tighter">{{ number_format($broadcast->recipients_count) }}</p>
                    </div>
                </div>

                <div class="p-6 bg-slate-50/50 rounded-2xl border border-slate-100">
                    <p class="text-sm text-slate-600 font-medium leading-relaxed">{{ $broadcast->message }}</p>
                </div>

                <div class="mt-6 flex items-center justify-between">
                    <div class="flex items-center space-x-2 text-[9px] font-bold text-slate-400 uppercase">
                        <i class="fas fa-check-circle text-emerald-500"></i>
                        <span>Delivered to {{ ucfirst($broadcast->target_audience) }}</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button class="text-[9px] font-black text-slate-300 uppercase tracking-widest hover:text-blue-600 transition-colors">
                            <i class="fas fa-chart-line mr-1"></i> View Analytics
                        </button>
                    </div>
                </div>
            </div>
            @empty
            <div class="text-center py-20">
                <div class="w-20 h-20 bg-slate-50 rounded-[2.5rem] flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-broadcast-tower text-3xl text-slate-200"></i>
                </div>
                <h3 class="text-lg font-black text-slate-900 tracking-tight mb-2">No Broadcasts Yet</h3>
                <p class="text-sm text-slate-400 font-medium">Start engaging with your patient community by creating your first broadcast.</p>
                <button type="button" data-cura-modal-open="broadcast-modal" class="mt-6 px-6 py-3 bg-slate-900 text-white rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg active:scale-95 transition-all">
                    Create First Broadcast
                </button>
            </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Broadcast Modal -->
<x-modal id="broadcast-modal" max-width="2xl" body-class="p-0">
        <form action="{{ route('vendor.broadcast.store') }}" method="POST">
            @csrf
            <div class="px-10 py-10 bg-slate-900 text-white relative overflow-hidden">
                <div class="relative z-10">
                    <h3 class="text-2xl font-black tracking-tight">New Broadcast</h3>
                    <p class="text-blue-400 text-[10px] font-black uppercase tracking-[0.2em] mt-2 italic">Community Alert System</p>
                </div>
                <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-blue-600/20 rounded-full blur-3xl"></div>
            </div>
            
            <div class="p-10">
                <div class="mb-6">
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3">Broadcast Type</label>
                    <div class="grid grid-cols-3 gap-4">
                        <label class="relative cursor-pointer">
                            <input type="radio" name="type" value="general" required class="peer sr-only">
                            <div class="p-4 bg-slate-50 border-2 border-transparent peer-checked:border-emerald-500 peer-checked:bg-emerald-50 rounded-2xl transition-all text-center">
                                <i class="fas fa-bullhorn text-2xl text-slate-300 peer-checked:text-emerald-600 mb-2"></i>
                                <p class="text-[9px] font-black text-slate-400 peer-checked:text-emerald-600 uppercase">General</p>
                            </div>
                        </label>
                        <label class="relative cursor-pointer">
                            <input type="radio" name="type" value="stock" required class="peer sr-only">
                            <div class="p-4 bg-slate-50 border-2 border-transparent peer-checked:border-blue-500 peer-checked:bg-blue-50 rounded-2xl transition-all text-center">
                                <i class="fas fa-box-open text-2xl text-slate-300 peer-checked:text-blue-600 mb-2"></i>
                                <p class="text-[9px] font-black text-slate-400 peer-checked:text-blue-600 uppercase">Stock Alert</p>
                            </div>
                        </label>
                        <label class="relative cursor-pointer">
                            <input type="radio" name="type" value="emergency" required class="peer sr-only">
                            <div class="p-4 bg-slate-50 border-2 border-transparent peer-checked:border-red-500 peer-checked:bg-red-50 rounded-2xl transition-all text-center">
                                <i class="fas fa-exclamation-triangle text-2xl text-slate-300 peer-checked:text-red-600 mb-2"></i>
                                <p class="text-[9px] font-black text-slate-400 peer-checked:text-red-600 uppercase">Emergency</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3">Broadcast Title</label>
                    <input type="text" name="title" required maxlength="100" placeholder="e.g., New COVID-19 Vaccines Available" 
                        class="w-full px-5 py-3.5 bg-slate-50 border border-transparent rounded-xl text-sm font-bold focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-100 outline-none transition-all">
                </div>

                <div class="mb-8">
                    <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3">Message Content</label>
                    <textarea name="message" required maxlength="1000" rows="5" placeholder="Compose your message to the community..."
                        class="w-full px-5 py-3.5 bg-slate-50 border border-transparent rounded-xl text-sm font-medium focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-100 outline-none transition-all resize-none"></textarea>
                    <p class="text-[8px] font-bold text-slate-300 uppercase tracking-widest mt-2 text-right">Max 1000 characters</p>
                </div>

                <div class="p-4 bg-blue-50 border border-blue-100 rounded-2xl mb-8">
                    <div class="flex items-start space-x-3">
                        <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                        <div>
                            <p class="text-[9px] font-black text-blue-600 uppercase tracking-widest mb-1">Broadcast Reach</p>
                            <p class="text-xs text-blue-600 font-medium">This message will be sent to <strong>{{ number_format($followerCount) }} followers</strong> who have saved your pharmacy.</p>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full bg-slate-900 text-white py-5 rounded-[1.5rem] font-black text-xs uppercase tracking-[0.2em] shadow-xl hover:bg-blue-600 transition-all active:scale-95 flex items-center justify-center space-x-3">
                    <span>Dispatch Broadcast</span>
                    <i class="fas fa-paper-plane text-[10px]"></i>
                </button>
                <button type="button" data-cura-modal-close class="w-full mt-4 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest hover:text-slate-600 transition-colors">
                    Cancel Operation
                </button>
            </div>
        </form>
</x-modal>
@endsection
