<?php

return [
    //plattform-spezifische Einstellungen
    'vsshop'=>[
        'transfer'=>[
            //Paketgrösse, Grösse bezieht sich auf Anzahl der Datensätze
            'limit'=>100,
            //Anzahl der fehlgeschlagenen Versuche, ein Paket zu versenden. Danach muss der gesamte Sendeprozess abgebrochen werden!
            'max_failed_attempts' => 5,
        ],
        'api'=>[
            'syncro_path' => 'api/v1/process-syncros',
        ],
    ],
    'check24'=>[

        'ftp'=>[
            //Die Verzeichnisnamen entsprechen dabei aus unserer Sicht!
                'import_folder'=>'outbound',
                'export_folder'=>'inbound',
                'backup_folder'=>'backup',

            //Productfeed-Datei-Name
            'file_name_productfeed'=>'productfeed.csv',
            //Order-Datei-Name
            'file_name_order'=>'ORDER.xml',
        ],
        'internal_import_folder' => 'check24_import_internal',
        'internal_import_folder_order' => 'check24_import_internal/order',
        'internal_export_folder' => 'check24_export_internal',
        'internal_import_folder_order_cancelled' => 'check24_import_internal/order/cancelled',

        'orders_productive'=>[
            'wunderschoen-mode','melchior',
        ],
        //wir konfigurieren vorübergehend die Packscheine und Retourenscheine für die Check24 Kunden hier fest
        "receipts" =>[
            //Modemai wieder löschen
            'modemai'=>[
                'picklist'=>[
                    'view'=>'tenant.pdf.provider.check24.packschein',
                ],
                'retoure'=>[
                    'view'=>'tenant.pdf.provider.check24.retoure',
                    'text'=>'1.Retourengrund (Nummer) eintragen<br>2. Artikel inkl. Retourenschein verpacken<br>3. Tätigen Sie den Widerruf der Ware im Kundenkonto. Anschließend bekommen Sie das Retourenlabel zugeschickt<br>4. Rücksendelabel auf Paket aufkleben<br>5. Rücksendung zur Postfiliale bringen<br>6. Den Rücksendebeleg des Versandunternehmens aufbewahren',
                    'option'=> [
                        'price' => 0.3,
                        'text'=>'joghurt',
                    ],
                ],
                'delivery'=>[
                    'view'=>'tenant.pdf.provider.check24.lieferschein',
                ],
            ],
            'wunderschoen-mode'=>[
                'picklist'=>[
                    'view'=>'tenant.pdf.provider.check24.packschein',
                ],
                'retoure'=>[
                    'view'=>'tenant.pdf.provider.check24.retoure',
                    'text'=>'1. Retourengrund (Nummer) eintragen<br>2. Artikel inkl. Retourenschein verpacken<br>3. Paket ausreichend frankieren und in einer Postfiliale Ihrer Wahl abgeben<br>4. Den Rücksendebeleg des Versandunternehmens aufbewahren<br>',
                ],
                'delivery'=>[
                    'view'=>'tenant.pdf.provider.check24.lieferschein',
                ],
            ],
            'zoergiebel'=>[
                'picklist'=>[
                    'view'=>'tenant.pdf.provider.check24.packschein',
                ],
                'retoure'=>[
                    'view'=>'tenant.pdf.provider.check24.retoure',
                    'text'=>'1. Retourengrund (Nummer) eintragen<br>2. Artikel inkl. Retourenschein verpacken<br>3. Paket ausreichend frankieren und in einer Postfiliale Ihrer Wahl abgeben<br>4. Den Rücksendebeleg des Versandunternehmens aufbewahren<br>',
                    'option'=> [
                        'price' => 49.90,
                        'text'=>'1. Retourengrund (Nummer) eintragen<br>2. Artikel inkl. Retourenschein verpacken3. Das beiliegende Rücksendelabel auf Paket aufkleben<br>4. Rücksendung zur Postfiliale bringen<br>5. Den Rücksendebeleg des Versandunternehmens aufbewahren',
                    ],
                ],
                'delivery'=>[
                    'view'=>'tenant.pdf.provider.check24.lieferschein',
                ],
            ],
            'schwoeppe'=>[
                'picklist'=>[
                    'view'=>'tenant.pdf.provider.check24.packschein',
                ],
                'retoure'=>[
                    'view'=>'tenant.pdf.provider.check24.retoure',
                    'text'=>'1. Retourengrund (Nummer) eintragen<br>2. Artikel inkl. Retourenschein verpacken<br>3. Das beiliegende Rücksendelabel auf Paket aufkleben<br>4. Rücksendung zur Postfiliale bringen<br>5. Den Rücksendebeleg des Versandunternehmens aufbewahren',
                ],
                'delivery'=>[
                    'view'=>'tenant.pdf.provider.check24.lieferschein',
                ],
            ],
            'melchior'=>[
                'picklist'=>[
                    'view'=>'tenant.pdf.provider.check24.packschein',
                ],
                'retoure'=>[
                    'view'=>'tenant.pdf.provider.check24.retoure',
                    'text'=>'1. Retourengrund (Nummer) eintragen<br>2. Artikel inkl. Retourenschein verpacken<br>3. Paket ausreichend frankieren und in einer Postfiliale Ihrer Wahl abgeben<br>4. Den Rücksendebeleg des Versandunternehmens aufbewahren<br>',
                ],
                'delivery'=>[
                    'view'=>'tenant.pdf.provider.check24.lieferschein',
                ],
            ],
            'fashionundtrends'=>[
                'picklist'=>[
                    'view'=>'tenant.pdf.provider.check24.packschein',
                ],
                'retoure'=>[
                    'view'=>'tenant.pdf.provider.check24.retoure',
                    'text'=>'1. Retourengrund (Nummer) eintragen<br>2. Artikel inkl. Retourenschein verpacken<br>3. Paket ausreichend frankieren und in einer Postfiliale Ihrer Wahl abgeben<br>4. Den Rücksendebeleg des Versandunternehmens aufbewahren<br>',
                ],
                'delivery'=>[
                    'view'=>'tenant.pdf.provider.check24.lieferschein',
                ],
            ],
        ]

        ],
];
