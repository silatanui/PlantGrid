<?php

namespace App\Support;

class DemoContact
{
    /**
     * Deterministic fictional contact details for Northridge demo users.
     *
     * @return array{
     *     job_title: string,
     *     phone: string,
     *     mobile: string,
     *     extension: string,
     *     address: string,
     *     city: string,
     *     postal_code: string,
     *     country: string
     * }
     */
    public static function for(string $email, ?string $departmentCode = null, ?string $roleName = null): array
    {
        $seed = abs(crc32(strtolower(trim($email))));
        $extension = (string) (2100 + ($seed % 6899));
        $mobileBody = str_pad((string) ($seed % 10000000), 7, '0', STR_PAD_LEFT);
        $mobilePrefix = 10 + ($seed % 89);

        $streets = [
            'Plant Access Road',
            'Packaging Avenue',
            'Admin Block Lane',
            'Intake Yard Road',
            'Warehouse Spur',
            'QA Crescent',
            'Maintenance Drive',
            'ICT Corridor',
        ];
        $street = $streets[$seed % count($streets)];
        $plot = 12 + ($seed % 86);

        return [
            'job_title' => self::jobTitle($roleName, $departmentCode),
            'phone' => sprintf('+254 20 %03d %04d', 410 + ($seed % 480), (int) $extension),
            'mobile' => sprintf('+254 7%02d %s %s', $mobilePrefix, substr($mobileBody, 0, 3), substr($mobileBody, 3, 4)),
            'extension' => $extension,
            'address' => "Plot {$plot}, {$street}, Northridge Processing Estate",
            'city' => 'Northridge',
            'postal_code' => sprintf('NR-%04d', 1000 + ($seed % 9000)),
            'country' => 'Kenya (demo)',
        ];
    }

    private static function jobTitle(?string $roleName, ?string $departmentCode): string
    {
        $role = $roleName ?: 'Staff';
        $dept = strtoupper((string) $departmentCode);

        return match (true) {
            $role === 'CEO' => 'Chief Executive Officer',
            $role === 'System Administrator' => 'Platform administrator',
            $role === 'ICT Manager' => 'ICT manager',
            $role === 'ICT Technician' => 'ICT technician',
            $role === 'Auditor' => 'ICT auditor',
            $role === 'Department Manager' && $dept === 'PRD' => 'Production manager',
            $role === 'Department Manager' && $dept === 'QA' => 'Quality assurance manager',
            $role === 'Department Manager' && $dept === 'WHS' => 'Warehouse manager',
            $role === 'Department Manager' && $dept === 'MNT' => 'Maintenance manager',
            $role === 'Department Manager' && $dept === 'FIN' => 'Finance manager',
            $role === 'Department Manager' => 'Department manager',
            $dept === 'PRD' => 'Production operative',
            $dept === 'QA' => 'Quality assurance officer',
            $dept === 'WHS' => 'Warehouse operative',
            $dept === 'MNT' => 'Maintenance technician',
            $dept === 'FIN' => 'Finance clerk',
            $dept === 'ICT' => 'ICT support officer',
            default => 'Plant staff',
        };
    }
}
