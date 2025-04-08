<?php

use Symfony\Component\Translation\MessageCatalogue;

$catalogue = new MessageCatalogue('nl-NL', array (
));

$catalogueNl = new MessageCatalogue('nl', array (
));
$catalogue->addFallbackCatalogue($catalogueNl);

return $catalogue;
