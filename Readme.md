# Soisy free
## Modulo Soisy per prestashop 1.6, 1.7

[![N|Solid](https://www.kforge.it/images/KForge-Agenzia-Web-TorinoX2.png)](https://www.kforge.it)

## Features
- installazione e disinstallazione secondo gli standard di PrestShop con inserimento dello status ordine "In attesa pagamento Soisy";
- configurazione in backoffice con:
- live mode/sandbox mode;
- credenziali;
- il minimo rateizzabile è inserito in fase di installazione e non configurabile 
- è presente il widget ufficiale per la per la simulazione rateale sulla scheda prodotto e sul bottone di checkout;
- se il minimo rateizzabile è eguagliato o superato il sistema proporrà il pagamento durante il checkout
## Comportamento al checkout
- una volta cliccato "Paga con Soisy" verrà chiuso l'ordine su PrestaShop con lo status predefinito "In attesa pagamento Soisy" e l'utente viene reindirizzato sul funnel Soisy;
- tutti gli update gestibili da callback non saranno gestiti, l'amministratore dello shop deve prevedere in backoffice i propri status ed aggiornarli a mano.
- è prevista una modalità "debug", se attiva le chiamate a soisy verranno salvate nella cartella log del modulo
- se l'utente abbandona il funnel soisy agendo sui tasti (e non con la freccia "Indietro" del browser) lo status dell'ordine verrà impostato come annullato.

## Installazione

Il modulo richiede [PHP](https://www.php.net/) 7.1+
[Prestashop](https://www.prestashop.com/) 1.6.1.24 o 1.7+

Caricare il modulo dal proprio backoffice, seguire le istruzioni.
