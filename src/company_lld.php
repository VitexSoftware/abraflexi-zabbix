<?php

declare(strict_types=1);

/**
 * This file is part of the EaseCore package.
 *
 * (c) Vítězslav Dvořák <info@vitexsoftware.cz>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AbraFlexi\Zabbix;

require_once '../vendor/autoload.php';

\define('APP_NAME', 'AbraFlexi-Zabbix-Low-Level-Discovery');

/**
 * Generate Zabbix Low Level Discovery JSON for AbraFlexi companies.
 */
function generateCompanyLLD(): void
{
    try {
        // Parse command line arguments
        $options = getopt('m::e::', ['mode::', 'env::']);

        // Get the path to the .env file
        $envfile = $options['env'] ?? '../.env';
        \Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD'], $envfile);

        $checker = new \AbraFlexi\Company();
        $listing = $checker->getAllFromAbraFlexi();

        if (!\is_array($listing)) {
            throw new \Exception('Failed to retrieve company list from AbraFlexi API');
        }

        // Transform data to Zabbix LLD format
        $lldData = [];

        foreach ($listing as $company) {
            if (!isset($company['dbNazev']) || !isset($company['nazev'])) {
                continue; // Skip invalid entries
            }

            $lldData[] = [
                '{#COMPANY_CODE}' => $company['dbNazev'],
                '{#COMPANY_NAME}' => $company['nazev'],
                '{#COMPANY_DB}' => $company['dbNazev'],
                '{#COMPANY_ID}' => (string) ($company['id'] ?? ''),
                '{#COMPANY_STATE}' => $company['stavEnum'] ?? '',
                '{#COMPANY_SHOW}' => $company['show'] ? '1' : '0',
                '{#COMPANY_WATCHING}' => $company['watchingChanges'] ? '1' : '0',
            ];
        }

        // Output Zabbix LLD JSON
        echo json_encode($lldData, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        exit(0);
    } catch (\Exception $e) {
        // Log error and return empty JSON array for Zabbix
        error_log('AbraFlexi Zabbix LLD Error: '.$e->getMessage());
        echo json_encode([]);

        exit(1);
    }
}

// Execute the function
generateCompanyLLD();
