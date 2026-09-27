<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ISO 3166-1 countries with international dialling code and ISO 4217 currency.
 * Idempotent: existing rows (matched by iso2) are left as the admin edited them.
 */
class CountriesSeeder extends Seeder
{
    /** @return list<array{0: string, 1: string, 2: string, 3: string}> [iso2, name, dial code, currency] */
    public static function countries(): array
    {
        return [
            ['AF', 'Afghanistan', '+93', 'AFN'], ['AL', 'Albania', '+355', 'ALL'], ['DZ', 'Algeria', '+213', 'DZD'],
            ['AD', 'Andorra', '+376', 'EUR'], ['AO', 'Angola', '+244', 'AOA'], ['AG', 'Antigua and Barbuda', '+1', 'XCD'],
            ['AR', 'Argentina', '+54', 'ARS'], ['AM', 'Armenia', '+374', 'AMD'], ['AU', 'Australia', '+61', 'AUD'],
            ['AT', 'Austria', '+43', 'EUR'], ['AZ', 'Azerbaijan', '+994', 'AZN'], ['BS', 'Bahamas', '+1', 'BSD'],
            ['BH', 'Bahrain', '+973', 'BHD'], ['BD', 'Bangladesh', '+880', 'BDT'], ['BB', 'Barbados', '+1', 'BBD'],
            ['BY', 'Belarus', '+375', 'BYN'], ['BE', 'Belgium', '+32', 'EUR'], ['BZ', 'Belize', '+501', 'BZD'],
            ['BJ', 'Benin', '+229', 'XOF'], ['BT', 'Bhutan', '+975', 'BTN'], ['BO', 'Bolivia', '+591', 'BOB'],
            ['BA', 'Bosnia and Herzegovina', '+387', 'BAM'], ['BW', 'Botswana', '+267', 'BWP'], ['BR', 'Brazil', '+55', 'BRL'],
            ['BN', 'Brunei', '+673', 'BND'], ['BG', 'Bulgaria', '+359', 'BGN'], ['BF', 'Burkina Faso', '+226', 'XOF'],
            ['BI', 'Burundi', '+257', 'BIF'], ['CV', 'Cabo Verde', '+238', 'CVE'], ['KH', 'Cambodia', '+855', 'KHR'],
            ['CM', 'Cameroon', '+237', 'XAF'], ['CA', 'Canada', '+1', 'CAD'], ['CF', 'Central African Republic', '+236', 'XAF'],
            ['TD', 'Chad', '+235', 'XAF'], ['CL', 'Chile', '+56', 'CLP'], ['CN', 'China', '+86', 'CNY'],
            ['CO', 'Colombia', '+57', 'COP'], ['KM', 'Comoros', '+269', 'KMF'], ['CG', 'Congo', '+242', 'XAF'],
            ['CD', 'Congo (Democratic Republic)', '+243', 'CDF'], ['CR', 'Costa Rica', '+506', 'CRC'], ['CI', "Côte d'Ivoire", '+225', 'XOF'],
            ['HR', 'Croatia', '+385', 'EUR'], ['CU', 'Cuba', '+53', 'CUP'], ['CY', 'Cyprus', '+357', 'EUR'],
            ['CZ', 'Czechia', '+420', 'CZK'], ['DK', 'Denmark', '+45', 'DKK'], ['DJ', 'Djibouti', '+253', 'DJF'],
            ['DM', 'Dominica', '+1', 'XCD'], ['DO', 'Dominican Republic', '+1', 'DOP'], ['EC', 'Ecuador', '+593', 'USD'],
            ['EG', 'Egypt', '+20', 'EGP'], ['SV', 'El Salvador', '+503', 'USD'], ['GQ', 'Equatorial Guinea', '+240', 'XAF'],
            ['ER', 'Eritrea', '+291', 'ERN'], ['EE', 'Estonia', '+372', 'EUR'], ['SZ', 'Eswatini', '+268', 'SZL'],
            ['ET', 'Ethiopia', '+251', 'ETB'], ['FJ', 'Fiji', '+679', 'FJD'], ['FI', 'Finland', '+358', 'EUR'],
            ['FR', 'France', '+33', 'EUR'], ['GA', 'Gabon', '+241', 'XAF'], ['GM', 'Gambia', '+220', 'GMD'],
            ['GE', 'Georgia', '+995', 'GEL'], ['DE', 'Germany', '+49', 'EUR'], ['GH', 'Ghana', '+233', 'GHS'],
            ['GR', 'Greece', '+30', 'EUR'], ['GD', 'Grenada', '+1', 'XCD'], ['GT', 'Guatemala', '+502', 'GTQ'],
            ['GN', 'Guinea', '+224', 'GNF'], ['GW', 'Guinea-Bissau', '+245', 'XOF'], ['GY', 'Guyana', '+592', 'GYD'],
            ['HT', 'Haiti', '+509', 'HTG'], ['HN', 'Honduras', '+504', 'HNL'], ['HK', 'Hong Kong', '+852', 'HKD'],
            ['HU', 'Hungary', '+36', 'HUF'], ['IS', 'Iceland', '+354', 'ISK'], ['IN', 'India', '+91', 'INR'],
            ['ID', 'Indonesia', '+62', 'IDR'], ['IR', 'Iran', '+98', 'IRR'], ['IQ', 'Iraq', '+964', 'IQD'],
            ['IE', 'Ireland', '+353', 'EUR'], ['IL', 'Israel', '+972', 'ILS'], ['IT', 'Italy', '+39', 'EUR'],
            ['JM', 'Jamaica', '+1', 'JMD'], ['JP', 'Japan', '+81', 'JPY'], ['JO', 'Jordan', '+962', 'JOD'],
            ['KZ', 'Kazakhstan', '+7', 'KZT'], ['KE', 'Kenya', '+254', 'KES'], ['KI', 'Kiribati', '+686', 'AUD'],
            ['KP', 'Korea (North)', '+850', 'KPW'], ['KR', 'Korea (South)', '+82', 'KRW'], ['XK', 'Kosovo', '+383', 'EUR'],
            ['KW', 'Kuwait', '+965', 'KWD'], ['KG', 'Kyrgyzstan', '+996', 'KGS'], ['LA', 'Laos', '+856', 'LAK'],
            ['LV', 'Latvia', '+371', 'EUR'], ['LB', 'Lebanon', '+961', 'LBP'], ['LS', 'Lesotho', '+266', 'LSL'],
            ['LR', 'Liberia', '+231', 'LRD'], ['LY', 'Libya', '+218', 'LYD'], ['LI', 'Liechtenstein', '+423', 'CHF'],
            ['LT', 'Lithuania', '+370', 'EUR'], ['LU', 'Luxembourg', '+352', 'EUR'], ['MO', 'Macao', '+853', 'MOP'],
            ['MG', 'Madagascar', '+261', 'MGA'], ['MW', 'Malawi', '+265', 'MWK'], ['MY', 'Malaysia', '+60', 'MYR'],
            ['MV', 'Maldives', '+960', 'MVR'], ['ML', 'Mali', '+223', 'XOF'], ['MT', 'Malta', '+356', 'EUR'],
            ['MH', 'Marshall Islands', '+692', 'USD'], ['MR', 'Mauritania', '+222', 'MRU'], ['MU', 'Mauritius', '+230', 'MUR'],
            ['MX', 'Mexico', '+52', 'MXN'], ['FM', 'Micronesia', '+691', 'USD'], ['MD', 'Moldova', '+373', 'MDL'],
            ['MC', 'Monaco', '+377', 'EUR'], ['MN', 'Mongolia', '+976', 'MNT'], ['ME', 'Montenegro', '+382', 'EUR'],
            ['MA', 'Morocco', '+212', 'MAD'], ['MZ', 'Mozambique', '+258', 'MZN'], ['MM', 'Myanmar', '+95', 'MMK'],
            ['NA', 'Namibia', '+264', 'NAD'], ['NR', 'Nauru', '+674', 'AUD'], ['NP', 'Nepal', '+977', 'NPR'],
            ['NL', 'Netherlands', '+31', 'EUR'], ['NZ', 'New Zealand', '+64', 'NZD'], ['NI', 'Nicaragua', '+505', 'NIO'],
            ['NE', 'Niger', '+227', 'XOF'], ['NG', 'Nigeria', '+234', 'NGN'], ['MK', 'North Macedonia', '+389', 'MKD'],
            ['NO', 'Norway', '+47', 'NOK'], ['OM', 'Oman', '+968', 'OMR'], ['PK', 'Pakistan', '+92', 'PKR'],
            ['PW', 'Palau', '+680', 'USD'], ['PS', 'Palestine', '+970', 'ILS'], ['PA', 'Panama', '+507', 'PAB'],
            ['PG', 'Papua New Guinea', '+675', 'PGK'], ['PY', 'Paraguay', '+595', 'PYG'], ['PE', 'Peru', '+51', 'PEN'],
            ['PH', 'Philippines', '+63', 'PHP'], ['PL', 'Poland', '+48', 'PLN'], ['PT', 'Portugal', '+351', 'EUR'],
            ['PR', 'Puerto Rico', '+1', 'USD'], ['QA', 'Qatar', '+974', 'QAR'], ['RO', 'Romania', '+40', 'RON'],
            ['RU', 'Russia', '+7', 'RUB'], ['RW', 'Rwanda', '+250', 'RWF'], ['KN', 'Saint Kitts and Nevis', '+1', 'XCD'],
            ['LC', 'Saint Lucia', '+1', 'XCD'], ['VC', 'Saint Vincent and the Grenadines', '+1', 'XCD'], ['WS', 'Samoa', '+685', 'WST'],
            ['SM', 'San Marino', '+378', 'EUR'], ['ST', 'Sao Tome and Principe', '+239', 'STN'], ['SA', 'Saudi Arabia', '+966', 'SAR'],
            ['SN', 'Senegal', '+221', 'XOF'], ['RS', 'Serbia', '+381', 'RSD'], ['SC', 'Seychelles', '+248', 'SCR'],
            ['SL', 'Sierra Leone', '+232', 'SLE'], ['SG', 'Singapore', '+65', 'SGD'], ['SK', 'Slovakia', '+421', 'EUR'],
            ['SI', 'Slovenia', '+386', 'EUR'], ['SB', 'Solomon Islands', '+677', 'SBD'], ['SO', 'Somalia', '+252', 'SOS'],
            ['ZA', 'South Africa', '+27', 'ZAR'], ['SS', 'South Sudan', '+211', 'SSP'], ['ES', 'Spain', '+34', 'EUR'],
            ['LK', 'Sri Lanka', '+94', 'LKR'], ['SD', 'Sudan', '+249', 'SDG'], ['SR', 'Suriname', '+597', 'SRD'],
            ['SE', 'Sweden', '+46', 'SEK'], ['CH', 'Switzerland', '+41', 'CHF'], ['SY', 'Syria', '+963', 'SYP'],
            ['TW', 'Taiwan', '+886', 'TWD'], ['TJ', 'Tajikistan', '+992', 'TJS'], ['TZ', 'Tanzania', '+255', 'TZS'],
            ['TH', 'Thailand', '+66', 'THB'], ['TL', 'Timor-Leste', '+670', 'USD'], ['TG', 'Togo', '+228', 'XOF'],
            ['TO', 'Tonga', '+676', 'TOP'], ['TT', 'Trinidad and Tobago', '+1', 'TTD'], ['TN', 'Tunisia', '+216', 'TND'],
            ['TR', 'Türkiye', '+90', 'TRY'], ['TM', 'Turkmenistan', '+993', 'TMT'], ['TV', 'Tuvalu', '+688', 'AUD'],
            ['UG', 'Uganda', '+256', 'UGX'], ['UA', 'Ukraine', '+380', 'UAH'], ['AE', 'United Arab Emirates', '+971', 'AED'],
            ['GB', 'United Kingdom', '+44', 'GBP'], ['US', 'United States', '+1', 'USD'], ['UY', 'Uruguay', '+598', 'UYU'],
            ['UZ', 'Uzbekistan', '+998', 'UZS'], ['VU', 'Vanuatu', '+678', 'VUV'], ['VA', 'Vatican City', '+39', 'EUR'],
            ['VE', 'Venezuela', '+58', 'VES'], ['VN', 'Vietnam', '+84', 'VND'], ['YE', 'Yemen', '+967', 'YER'],
            ['ZM', 'Zambia', '+260', 'ZMW'], ['ZW', 'Zimbabwe', '+263', 'ZWG'],
        ];
    }

    public function run(): void
    {
        $now = now();
        $existing = DB::table('countries')->pluck('iso2')->flip();

        $rows = [];

        foreach (self::countries() as [$iso2, $name, $dialCode, $currency]) {
            if ($existing->has($iso2)) {
                continue;
            }

            $rows[] = [
                'iso2' => $iso2,
                'name' => $name,
                'dial_code' => $dialCode,
                'currency_code' => $currency,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('countries')->insert($chunk);
        }
    }
}
