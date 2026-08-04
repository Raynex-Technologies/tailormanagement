<?php

namespace App\Livewire\WhatsApp\Templates;

use App\Models\WhatsappIntegration;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\Templates\WhatsappTemplateService;
use App\Services\WhatsApp\Templates\WhatsappTemplateSyncService;
use App\Support\BranchContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')] #[Title('WhatsApp Templates')] class Index extends Component
{
    use AuthorizesRequests,WithPagination;

    public function mount(): void
    {
        $this->authorize('sms-templates.view');
    }

    public function sync(WhatsappTemplateSyncService $service): void
    {
        $this->authorize('sms-templates.update');
        $i = WhatsappIntegration::forBranch(BranchContext::getEffectiveBranchId());
        $result = $service->sync($i);
        Log::info('WhatsApp templates manually synchronized', ['branch_id' => $i->branch_id, 'success' => $result['success'], 'actor_id' => auth()->id()]);
        session()->flash($result['success'] ? 'success' : 'error', $result['success'] ? __(':count templates synchronized.', ['count' => $result['count']]) : __($result['error_message']));
    }

    public function duplicate(int $id, WhatsappTemplateService $service): void
    {
        $this->authorize('sms-templates.update');
        $t = WhatsappTemplate::findOrFail($id);
        $copy = $service->duplicate($t);
        session()->flash('success', __('Draft duplicated.'));
        $this->redirectRoute('whatsapp-templates.edit', ['template' => $copy->id], navigate: true);
    }

    public function delete(int $id, WhatsappTemplateService $service): void
    {
        $this->authorize('sms-templates.update');
        $t = WhatsappTemplate::findOrFail($id);
        $result = $service->delete($t);
        Log::info('WhatsApp template deletion requested', ['branch_id' => $t->branch_id, 'template_id' => $t->id, 'success' => $result['success'], 'actor_id' => auth()->id()]);
        session()->flash($result['success'] ? 'success' : 'error', $result['success'] ? __('Template deleted.') : __($result['error_message']));
    }

    public function render()
    {
        return view('livewire.whatsapp.templates.index', ['templates' => WhatsappTemplate::query()->latest()->paginate(20)]);
    }
}
