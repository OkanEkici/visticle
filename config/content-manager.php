<?php

return [
    "operations" => [
        "insert" => 1,
        "update" => 2,
        "delete" => 3,
    ],
    "priorities" => [
        "immediate" => 1,
        "scheduled" => 2,
    ],
    //Wenn der Content-Manager Operationen wegen einem Artikel in Gang setzen muss,
    //die aufgrund Änderungen vieler Datensätze zustande kommen, die mit dem Artikel verknüpft sind.
    "context" => [

    ],
    "controller"=>[
        "article_controller"=>[
            'send_articles_to_shop' =>[
                "file_output_path" => "/content_manager/controller/article_controller/send_articles_to_shop",
                "file_output" => "file_output.txt"
            ]
        ]
    ]
];
