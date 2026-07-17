<?php

namespace App\Enums;

enum DocumentType: string
{
    case ReportCard = 'report_card';
    case CertificateCompletion = 'certificate_completion';
    case TransferCertificate = 'transfer_certificate';
    case FeeReceipt = 'fee_receipt';
    case StudentIdCard = 'student_id_card';

    public function label(): string
    {
        return match ($this) {
            self::ReportCard => 'Report Card',
            self::CertificateCompletion => 'Certificate of Completion',
            self::TransferCertificate => 'Transfer Certificate',
            self::FeeReceipt => 'Fee Receipt',
            self::StudentIdCard => 'Student ID Card',
        };
    }

    /**
     * @return list<self>
     */
    public static function options(): array
    {
        return self::cases();
    }

    /**
     * Document types the given user may generate / preview / print.
     * Finance is limited to fee-related operational docs (not academic transcripts).
     *
     * @return list<self>
     */
    public static function forUser(\App\Models\User $user): array
    {
        if ($user->isAdmin()) {
            return self::cases();
        }

        if ($user->isFinance()) {
            return [self::FeeReceipt, self::StudentIdCard];
        }

        return [];
    }

    public function allowedFor(\App\Models\User $user): bool
    {
        return in_array($this, self::forUser($user), true);
    }
}
