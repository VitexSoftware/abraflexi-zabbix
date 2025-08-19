declare(strict_types=1);

/**
 * This file is part of the AbraFlexi-Zabbix package
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AbraFlexi\Zabbix;

require_once '../vendor/autoload.php';

\define('APP_NAME', 'RBStatementsToAbraFlexiEvents');

// Parse command line arguments
$options = getopt('m::e::', ['mode::', 'env::']);

// Get the path to the .env file
$envfile = $options['env'] ?? '../.env';
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'], $envfile);
