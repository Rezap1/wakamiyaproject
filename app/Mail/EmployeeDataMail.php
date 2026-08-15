<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class EmployeeDataMail extends Mailable
{
    use Queueable, SerializesModels;

    public $employee;
    public $pdfPath;

    /**
     * Create a new message instance.
     */
    public function __construct(array $employee, ?string $pdfPath = null)
    {
        $this->employee = $employee;
        $this->pdfPath = $pdfPath;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Data Profil Karyawan - ' . ($this->employee['Full_Name'] ?? 'WMS'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.employee_data',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];
        if ($this->pdfPath && file_exists($this->pdfPath)) {
            $attachments[] = Attachment::fromPath($this->pdfPath)
                ->as('Profil_Karyawan_' . ($this->employee['Employee_Number'] ?? 'EMP') . '.pdf')
                ->withMime('application/pdf');
        }
        return $attachments;
    }
}
