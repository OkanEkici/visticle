<?php


/**
 * @author Tanju Özsoy <oezsoy@visc-media.de>
 *
 * 04.01.2021
 * Diese Konfigurationsdatei dient zum Abruf der aktuellen Steuersätze aus einer zentralen
 * Stelle. Wir haben hier zwei Hauptschlüssel auf letzter Ebene der Arrays:
 * "current" und "favored". Ist der Wert für fovored leer, so wird der Wert für current genommen,
 * ansonsten der andere favourisiert unter Berücksichtigung der zwingend erforderlichen Datumsangabe
 * unter "deadline" für "fovored".
 * Das ganze ist noch mal gegliedert nach dem Land/Region zur Berücksichtung landeseigener Steuersätze
 * für eine Internationalisierung des Unternehmens Visc Media.
 */
return
[
    'de'    =>  [
        'ordinary' => [
            'current'   =>  19,
            'favored'   =>  [
                'value' =>  19,
                'deadline'  => '01.01.2021',
            ],
        ],
        'reduced' => [
            'current'   =>  7,
        ]
    ],
    'en'    =>  [
        'ordinary' => [
            'current'   =>  19,
            'favored'   =>  [
                'value' =>  19,
                'deadline'  => '01.01.2021',
            ],
        ],
        'reduced' => [
            'current'   =>  7,
        ]
    ],
];
