<?php

namespace Tests\Feature\WhatsApp;

use App\Livewire\WhatsApp\Templates\Index;
use App\Models\Branch;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappTemplate;
use App\Support\BranchContext;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsappTemplateBranchRoutesTest extends TestCase
{
    public function test_fresh_http_requests_load_selected_branch_and_keep_foreign_templates_private(): void
    {
        $this->actingAsRole('admin');
        $own = $this->template($this->branch, 'selected_branch_template');
        $foreign = $this->template($this->otherBranch, 'foreign_branch_template');

        // Unlike component tests, a fresh PHP request has no static branch context.
        BranchContext::clear();
        $this->get(route('whatsapp-templates.index'))->assertOk()
            ->assertSee('selected_branch_template')->assertDontSee('foreign_branch_template');
        $this->assertSame($this->branch->id, BranchContext::id());
        BranchContext::clear();
        $this->get(route('whatsapp-templates.create'))->assertOk();
        BranchContext::clear();
        $this->get(route('whatsapp-templates.edit', $own->id))->assertOk();
        BranchContext::clear();
        $this->get(route('whatsapp-templates.edit', $foreign->id))->assertNotFound();
    }

    public function test_branch_staff_cannot_use_another_branch_session_selection(): void
    {
        $this->actingAsRole('branch_manager')->givePermissionTo('sms-templates.view');
        $this->template($this->branch, 'staff_branch_template');
        $this->withSession(['active_branch_id' => $this->otherBranch->id]);
        BranchContext::clear();
        $this->get(route('whatsapp-templates.index'))->assertOk()->assertSee('staff_branch_template');
        $this->assertSame($this->branch->id, BranchContext::id());
    }

    public function test_no_active_branch_has_a_safe_page_and_blocks_actions_without_provider_calls(): void
    {
        $this->actingAsRole('admin');
        $template = $this->template($this->branch, 'hidden_without_branch');
        Branch::query()->update(['is_active' => false]);
        BranchContext::clear();
        $this->get(route('whatsapp-templates.index'))->assertOk()
            ->assertSee('Select an active branch')->assertDontSee('hidden_without_branch');
        $this->get(route('whatsapp-templates.create'))->assertForbidden();
        Http::preventStrayRequests();
        Livewire::test(Index::class)->call('sync')->assertHasErrors('branch')
            ->call('delete', $template->id)->assertHasErrors('branch')
            ->call('duplicate', $template->id)->assertHasErrors('branch');
        $this->assertSame(1, WhatsappTemplate::withoutGlobalScopes()->count());
        Http::assertNothingSent();
    }

    private function template(Branch $branch, string $name): WhatsappTemplate
    {
        $integration = WhatsappIntegration::forBranch($branch->id);

        return WhatsappTemplate::withoutGlobalScopes()->create([
            'branch_id' => $branch->id, 'whatsapp_integration_id' => $integration->id,
            'name' => $name, 'language' => 'en', 'category' => 'UTILITY', 'local_state' => 'draft',
            'components' => [['type' => 'BODY', 'text' => 'Test message']],
        ]);
    }
}
