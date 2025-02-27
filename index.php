<?php
// index.php -- HotCRP home page
// Copyright (c) 2006-2019 Eddie Kohler; see LICENSE.

require_once("lib/navigation.php");
$nav = Navigation::get();

// handle `/u/USERINDEX/`
if ($nav->page === "u") {
    $unum = $nav->path_component(0);
    if ($unum !== false && ctype_digit($unum)) {
        if (!$nav->shift_path_components(2)) {
            // redirect `/u/USERINDEX` => `/u/USERINDEX/`
            Navigation::redirect($nav->server . $nav->base_path . "u/" . $unum . "/" . $nav->query);
        }
    } else {
        // redirect `/u/XXXX` => `/`
        Navigation::redirect($nav->server . $nav->base_path . $nav->query);
    }
}

// handle special pages
if ($nav->page === "images" || $nav->page === "scripts" || $nav->page === "stylesheets") {
    $_GET["file"] = $nav->page . $nav->path;
    include("cacheable.php");
    exit;
} else if ($nav->page === "api" || $nav->page === "cacheable") {
    include("{$nav->page}.php");
    exit;
}

require_once("src/initweb.php");
$page_template = $Conf->page_template($nav->page);

if (!$page_template) {
    header("HTTP/1.0 404 Not Found");
} else if (isset($page_template->group)) {
    // handle signin/signout -- may change $Me
    if ($page_template->name === "index") {
        $Me = Home_Partial::signin_requests($Me, $Qreq);
        // that also got rid of disabled users
    }
    $gx = new GroupedExtensions($Me, ["etc/pagepartials.json"],
                                $Conf->opt("pagePartials"));
    foreach ($gx->members($page_template->group) as $gj) {
        if ($gx->request($gj, $Qreq, [$Me, $Qreq, $gx, $gj]) === false)
            break;
    }
    $gx->start_render();
    foreach ($gx->members($page_template->group) as $gj) {
        if ($gx->render($gj, [$Me, $Qreq, $gx, $gj]) === false)
            break;
    }
    $gx->end_render();
} else {
    include($page_template->require);
}
