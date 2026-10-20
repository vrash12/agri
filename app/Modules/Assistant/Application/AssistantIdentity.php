<?php

namespace App\Modules\Assistant\Application;

use App\Models\User;

/** Application-owned introductions never need inference or operational queries. */
final class AssistantIdentity
{
    public function answer(User $user, string $kind, string $language = 'en', string $module = 'other', string $guide = AssistantGuide::DEFAULT, ?string $subjectGuide = null): string
    {
        $name = AssistantGuide::get($guide)['name'];
        $veterinary = $user->isProvincialVeterinaryOffice();
        $filipino = $language === 'fil';
        $limits = $filipino
            ? ($veterinary
                ? 'Animal Health lang ang saklaw ng iyong veterinary account. Maaari akong magpaliwanag ng animal-service workflows at magbasa ng pinapayagang ulat; hindi ako nagbabago o nag-aapruba ng records.'
                : 'Maaari akong magpaliwanag ng AgriLGU at magbasa ng records at ulat na pinapayagan ng iyong role at saklaw. Hindi ako nagbabago ng records, gumagawa ng account o nag-aapruba ng kahilingan.')
            : ($veterinary
                ? 'Your veterinary account is limited to Animal Health. I can explain animal-service workflows and read permitted reports; I cannot change or approve records.'
                : 'I can explain AgriLGU and read records and reports permitted by your role and area. I cannot change records, create accounts or approve requests.');

        $capabilities = $filipino
            ? ($veterinary
                ? 'May pangkalahatang gabay ako sa pag-aalaga ng hayop. Matutulungan kitang gamitin ang Animal Health at maintindihan ang pinapayagang ulat. Maaari ko ring ipaliwanag ang access at privacy ng Assistant.'
                : 'May sinuring gabay ako sa pananim, lupa, patubig, peste, alagang hayop at palaisdaan, at sa mga feature ng AgriLGU: farmer registry, parcels, crop plotting, satellite indices, El Niño readiness, assistance, harvests, Animal Health, cooperatives, machinery, dashboard, weather, geofences, accounts, backups at audit tools.')
            : ($veterinary
                ? 'I can explain general farm-animal care, help you use Animal Health and understand permitted reports. I can also explain Assistant access and privacy.'
                : 'I explain reviewed agriculture topics such as soil, crops, irrigation, pests, livestock and fish farming, and AgriLGU features: farmer registry, parcels, crop plotting, satellite indices, El Niño readiness, assistance, harvests, Animal Health, cooperatives, machinery, dashboard, weather, geofences, accounts, backups and audit tools.');
        $pageContext = $this->pageContext($module, $filipino);

        return match ($kind) {
            'guide' => $this->guideDescription($subjectGuide ?? $guide, $filipino).' '.$limits,
            'greeting' => ($filipino ? 'Kumusta! Ako si '.$name.', ang digital na gabay mo sa AgriLGU. Ano ang maitutulong ko?' : 'Hello! I’m '.$name.', your digital farm guide. How can I help with AgriLGU?').' '.$limits,
            'thanks' => $filipino ? 'Walang anuman! Sabihin lang kung may iba ka pang tanong tungkol sa AgriLGU.' : 'You’re welcome! Let me know if you have another question about AgriLGU.',
            'capabilities' => $capabilities.' '.$pageContext.' '.$limits.' '.$this->fileLimits($filipino),
            'context' => $pageContext.' '.$limits,
            'account' => $this->accountContext($user, $filipino).' '.$limits,
            'awareness' => $this->awareness($filipino, $name).' '.$pageContext.' '.$limits,
            'technology' => $this->technology($filipino).' '.$limits,
            default => ($filipino
                ? 'Ako si '.$name.', ang digital na gabay mo sa AgriLGU. Isa akong mascot ng software, hindi totoong tao. Gumagamit ang sinuring gabay sa sistema ng OpenAI. Lokal ang pagpapakilalang ito at walang AI request. Hindi ako opisyal na gumagawa ng pasya o kapalit ng iyong agriculture office.'
                : 'I’m '.$name.', your AgriLGU digital farm guide. I’m a software mascot, not a person. Reviewed system guidance uses OpenAI. This introduction is local and makes no AI request. I am not an official decision-maker or a replacement for your agriculture office.').' '.$pageContext.' '.$limits,
        };
    }

    private function guideDescription(string $id, bool $filipino): string
    {
        $guide = AssistantGuide::get($id);
        $character = match ($guide['id']) {
            'ka-kaloy' => $filipino ? 'mascot na kalabaw na magsasaka na may salakot' : 'carabao farmer mascot wearing a salakot',
            'aling-maya' => $filipino ? 'mascot na magsasakang Pilipina na may basket ng gulay' : 'Filipina farmer mascot carrying a basket of vegetables',
            'kiko' => $filipino ? 'mascot na kabataang Pilipinong gabay sa pagsasaka na may clipboard' : 'young Filipino farm-guide mascot holding a clipboard',
            default => $filipino ? 'mascot na kayumangging bakang magsasaka na may salakot' : 'brown cow farmer mascot wearing a salakot',
        };

        return $filipino
            ? 'Si '.$guide['name'].' ang '.$character.' ng AgriLGU. Iba-ibang anyo lang ng iisang helper ang apat na gabay; pareho ang kanilang kakayahan at access ayon sa iyong account. Para pumili ng gabay, pindutin ang larawan sa header ng chat. Mananatili ang usapan mo. Software mascot ito, hindi totoong tao o opisyal na gumagawa ng pasya. Gumagamit ng OpenAI ang sinuring gabay sa sistema. Lokal ang paliwanag na ito at walang AI request.'
            : $guide['name'].' is the AgriLGU '.$character.'. All four guides are appearances of the same helper, with the same abilities and access allowed by your account. To choose a guide, select the portrait in the chat header. Your conversation stays in place. This is a software mascot, not a person or an official decision-maker. Reviewed system guidance uses OpenAI. This description is local and makes no AI request.';
    }

    private function pageContext(string $module, bool $filipino): string
    {
        $label = match ($module) {
            'dashboard' => 'Dashboard',
            'farmers' => $filipino ? 'farmer registry at farmer records' : 'Farmers and farmer registry',
            'crops' => $filipino ? 'crop at parcel mapping' : 'Crop and parcel mapping',
            'satellite' => 'Satellite / NDVI',
            'climate-risk' => 'El Niño Readiness',
            'assistance' => $filipino ? 'agriculture assistance at seed releases' : 'Assistance and seed releases',
            'coverage' => $filipino ? 'assistance coverage' : 'Assistance coverage',
            'harvests' => $filipino ? 'harvest records' : 'Harvest records',
            'animal-health' => 'Animal Health',
            'cooperatives' => $filipino ? 'farmer cooperatives' : 'Farmer cooperatives',
            'machinery' => $filipino ? 'machinery inventory' : 'Machinery inventory',
            'accounts' => $filipino ? 'office accounts' : 'Office accounts',
            'geofences' => $filipino ? 'municipality geofences' : 'Municipality geofences',
            'backups' => 'Backup Folder',
            'audit' => $filipino ? 'audit trail' : 'Audit trail',
            default => null,
        };

        if ($label === null) {
            return $filipino
                ? 'Walang tiyak na pahinang ibinahagi sa chat na ito. Maaari pa rin akong tumulong sa sinuring gabay ng AgriLGU.'
                : 'No specific page has been shared with this chat. I can still help with reviewed AgriLGU guidance.';
        }

        // A browser-supplied module is only a relevance hint, never evidence of
        // the active filters, unsaved fields or permission to access that page.
        return $filipino
            ? 'Ang pahinang ibinahagi sa chat na ito ay '.$label.'. Gabay lang ito; hindi ko nakikita ang filters o hindi pa na-save na fields. Role at saklaw ng opisina mo pa rin ang kumokontrol sa access.'
            : 'The page shared with this chat is '.$label.'. It guides my help; I cannot see active filters or unsaved fields. Your role and office scope still control access.';
    }

    private function accountContext(User $user, bool $filipino): string
    {
        // Use the authenticated account, not identity or scope claims in chat.
        $role = $user->role_label;
        $scope = mb_substr($user->scopeLabel(), 0, 180);

        return $filipino
            ? 'Ang role ng naka-sign in mong account ay '.$role.'. Ang saklaw ng opisina mo ay '.$scope.'. Ito ang nakatakdang saklaw ng account, hindi isang kasalukuyang filter sa pahina.'
            : 'Your signed-in role is '.$role.'. Your office scope is '.$scope.'. This is your account assignment, not a current page filter.';
    }

    private function awareness(bool $filipino, string $name): string
    {
        return $filipino
            ? 'Ako si '.$name.', isang software helper; wala akong kamalayan o sariling pag-iisip tulad ng tao. Ginagamit ko ang sinuring gabay ng sistema, page context at pinapayagang follow-up references. Ang “Checking” ay palatandaan lang na pinoproseso ang request. Hindi ko iniimbak ang buong chat o binabasa ang server directories.'
            : 'I’m '.$name.', a software helper, not a conscious person. I use reviewed system guidance, page context and permitted follow-up references. “Checking” means a request is being processed. I do not store the full conversation or read server directories.';
    }

    private function technology(bool $filipino): string
    {
        $model = config('assistant.model');
        // Never echo an arbitrary environment value as a model identifier.
        $model = is_string($model) && preg_match('/^(?:gpt-|o[1-9](?:-|$))[a-z0-9.-]{0,70}$/D', $model) ? $model : null;
        $modelLabel = $model === null ? '' : ' ('.$model.')';

        return $filipino
            ? 'Gumagamit ang gabay sa sistema ng OpenAI'.$modelLabel.' sa pamamagitan ng Laravel AI SDK. Kinukuha ng RAG ang kaugnay na sinuring gabay ng AgriLGU bago sumagot. Aprubadong paksa at sinuring gabay lamang ang ipinapadala sa OpenAI, hindi ang aktuwal na chat o operational record text. Direktang binabasa sa AgriLGU ang pinapayagang office reports. Lokal ang sagot na ito at walang AI request. '.$this->fileLimits(true)
            : 'System guidance uses OpenAI'.$modelLabel.' through the Laravel AI SDK. RAG retrieves relevant reviewed AgriLGU guidance before answering. Only an application-owned topic and reviewed guidance are sent to OpenAI, never raw chat or operational record text. Permitted office reports are read directly inside AgriLGU. This answer is local and makes no AI request. '.$this->fileLimits(false);
    }

    private function fileLimits(bool $filipino): string
    {
        return $filipino
            ? 'Wala akong live browsing, filesystem o execution tools para magbukas ng server files o magpatakbo ng commands sa chat.'
            : 'I have no live browsing, filesystem or execution tools to open server files or run commands through this chat.';
    }
}
