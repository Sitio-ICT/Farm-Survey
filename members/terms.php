<?php
require __DIR__ . '/../functions/members/bootstrap.php';
require __DIR__ . '/_layout.php';

// DRAFT terms (version MEMBER_TERMS_VERSION) for legal review before launch.
layout_start('Terms of use', current_member());
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <div class="alert alert-warning small">Draft terms, version <?= e(MEMBER_TERMS_VERSION) ?>. To be reviewed by a lawyer before launch.</div>
                <h1 class="h3 text-gray-900">Terms of use</h1>
                <ol class="mt-3">
                    <li class="mb-2">One person, one account. You must be 18 or older and register with your own, valid government ID.</li>
                    <li class="mb-2">Collectors must obtain each respondent's informed consent before recording any answer, and must never invent, copy or alter survey data.</li>
                    <li class="mb-2">Earnings are held for a review period. Records later shown to be false are removed, the related earnings are reversed, and the account may be closed.</li>
                    <li class="mb-2">Buyers may use purchased data only under the dataset licence, and must not attempt to identify any respondent or resell the data.</li>
                    <li class="mb-2">We may suspend accounts that break these terms, and may report fraud to the authorities.</li>
                </ol>
            </div>
        </div>
    </div>
</div>
<?php
layout_end();
