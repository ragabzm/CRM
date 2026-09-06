<?php

declare(strict_types=1);

/*
 * What the product asks a model to do, in the reader's language.
 *
 * Here rather than inline at each call site, for the reason every string in
 * this directory is here: it is composed on the server, once, and a capability
 * must not be able to quietly ask for something the product did not agree to.
 * Reading them side by side is how somebody notices that one of them has
 * started asking a model to decide rather than to propose.
 */

return [
    'instruction' => [
        'summarise' => 'Summarise this support conversation in three sentences or fewer, for an agent picking it up. State only what is written; do not infer, advise, or promise anything.',
        'suggest_reply' => 'Draft up to three short replies an agent could send. Separate them with a line containing only ---. Propose wording; commit to nothing.',
        'propose_category' => 'Choose the single best category for this request from the numbered options. Answer with the number alone. If none fits, answer with nothing.',
        'suggest_articles' => 'List the ids of the offered articles that answer this question, separated by spaces. Use only ids from the list. If none answer it, answer with nothing.',
        'answer' => 'Answer the customer using only the articles provided. If they do not contain the answer, reply with nothing so a person can take over.',
    ],

    /*
     * The label every AI artefact carries. Built once, here, so no capability
     * can invent its own — and so nothing generated reaches a person without
     * saying what it is.
     */
    'label' => 'Suggested by AI — check before using',
];
