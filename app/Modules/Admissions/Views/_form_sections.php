<?php

$formAdmission = is_array($admission ?? null) ? $admission : [];
$includeTerms = (bool) ($includeTerms ?? false);
?>

<details class="mcc-admission-section mcc-admission-top-section" data-admission-section="personal" data-admission-section-group="personal" open>
    <summary class="mcc-admission-section-summary"><span>Personal Details</span></summary>
    <div class="mcc-admission-section-body" data-admission-section-slot="personal"></div>
</details>

<details class="mcc-admission-section mcc-admission-top-section" data-admission-section="coaching" data-admission-section-group="coaching">
    <summary class="mcc-admission-section-summary"><span>Coaching Plan</span></summary>
    <div class="mcc-admission-section-body" data-admission-section-slot="coaching"></div>
</details>

<details class="mcc-admission-section mcc-admission-top-section" data-admission-section="cricket" data-admission-section-group="cricket">
    <summary class="mcc-admission-section-summary"><span>Cricket Information</span></summary>
    <div class="mcc-admission-section-body" data-admission-section-slot="cricket"></div>
</details>

<details class="mcc-admission-section mcc-admission-top-section" data-admission-section="lead-ratings" data-admission-section-group="lead-ratings" open>
    <summary class="mcc-admission-section-summary"><span>Lead Information and Ratings</span></summary>
    <div class="mcc-admission-section-body" data-admission-section-slot="lead-ratings"></div>
</details>

<details class="mcc-admission-section mcc-admission-top-section" data-admission-section="fees" data-admission-section-group="fees">
    <summary class="mcc-admission-section-summary"><span>Fees</span></summary>
    <div class="mcc-admission-section-body">
        <div class="row mcc-admission-fields">
            <?= view('App\\Modules\\Admissions\\Views\\_fees_fields', ['admission' => $formAdmission]) ?>
        </div>
    </div>
</details>

<details class="mcc-admission-section mcc-admission-top-section" data-admission-section="coaching-installments" data-admission-section-group="coaching-installments">
    <summary class="mcc-admission-section-summary"><span>Coaching Instalments</span></summary>
    <div class="mcc-admission-section-body" data-admission-section-slot="coaching-installments"></div>
</details>

<details class="mcc-admission-section mcc-admission-top-section" data-admission-section="hostel-installments" data-admission-section-group="hostel-installments">
    <summary class="mcc-admission-section-summary"><span>Hostel Instalments</span></summary>
    <div class="mcc-admission-section-body" data-admission-section-slot="hostel-installments"></div>
</details>

<details class="mcc-admission-section mcc-admission-top-section" data-admission-section="declarations" data-admission-section-group="declarations" open>
    <summary class="mcc-admission-section-summary"><span>Declarations and Signatures</span></summary>
    <div class="mcc-admission-section-body" data-admission-section-slot="declarations">
        <?= view('App\\Modules\\Admissions\\Views\\_declarations_signatures', [
            'admission' => $formAdmission,
            'includeDocumentRecord' => false,
            'includeTerms' => $includeTerms,
        ]) ?>
    </div>
</details>
