<?php
require __DIR__ . '/../functions/members/bootstrap.php';
require __DIR__ . '/_layout.php';

// DRAFT for legal review before launch (Nigeria Data Protection Act 2023 / GAID 2025).
layout_start('Privacy notice', current_member());
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <div class="alert alert-warning small">Draft notice. To be reviewed by a data-protection lawyer and the DPO before launch.</div>
                <h1 class="h3 text-gray-900">Privacy notice for members</h1>
                <h2 class="h6 font-weight-bold mt-4">What we collect</h2>
                <p>Your name, date of birth, phone number, email address, state and LGA; the type and last 4 digits of the ID you verify with; a selfie; and, for driver's licences, passports and voter's cards, a photo of the ID.</p>
                <h2 class="h6 font-weight-bold mt-4">Why</h2>
                <p>To confirm you are a real, unique person, to prevent fraud and fake data, and to pay collectors. The legal basis is your consent and the contract you enter by registering.</p>
                <h2 class="h6 font-weight-bold mt-4">Who sees it</h2>
                <p>Your ID number and selfie are sent to a licensed identity-verification provider, which checks them against the national identity record. We do not store your ID number, only a coded fingerprint of it. Your selfie and ID photo are encrypted and can be opened only by our verification team; every opening is logged. Your identity details are never published or sold.</p>
                <h2 class="h6 font-weight-bold mt-4">How long</h2>
                <p>Selfies and ID photos are deleted 90 days after a verification decision, unless needed for an open fraud case. Account details are kept while your account is open and for the period the law requires after it closes.</p>
                <h2 class="h6 font-weight-bold mt-4">Your rights</h2>
                <p>You can ask to see, correct or delete your data, or withdraw consent, by contacting our Data Protection Officer. Withdrawing consent for identity checks means we cannot keep your account verified.</p>
            </div>
        </div>
    </div>
</div>
<?php
layout_end();
