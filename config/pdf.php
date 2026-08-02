<?php
return [
    'paper_size' => 'A4', // A4, Letter, Legal
    'orientation' => 'Portrait', // Portrait, Landscape
    'watermark' => [
        'enabled' => true,
        'text' => 'WAKAMIYA CONFIDENTIAL'
    ],
    'qr_code' => [
        'enabled' => true
    ],
    'digital_signature' => [
        'enabled' => true
    ],
    'template_mapping' => [
        'Salary Slip' => 'document.pdf.salary-slip',
        'Invoice' => 'document.pdf.invoice',
        'Receipt' => 'document.pdf.receipt',
        'Certificate' => 'document.pdf.certificate',
        'COE' => 'document.pdf.coe',
        'Visa' => 'document.pdf.visa',
        'Assessment' => 'document.pdf.assessment',
        'Training' => 'document.pdf.training',
        'Custom Document' => 'document.pdf.default'
    ]
];