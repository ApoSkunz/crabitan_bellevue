<?php
$pageTitle = __('footer.cgv');
$navLang   = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
$isBare    = $bare ?? false;
$isFr      = $navLang === 'fr';

require_once SRC_PATH . '/View/partials/legal-open.php';
?>

<?php if ($isFr) : ?>

        <p class="legal-update">
            <em>Dernière mise à jour : 3 avril 2026</em>
        </p>

        <p>
            Les présentes Conditions Générales de Vente (ci-après « CGV ») régissent l'ensemble des ventes de produits
            réalisées par <strong>GFA Bernard Solane &amp; Fils</strong>, propriétaire du Château Crabitan Bellevue,
            via le site internet <strong>crabitanbellevue.fr</strong>.
        </p>
        <p>
            Tout achat implique l'acceptation sans réserve des présentes CGV.
            GFA Bernard Solane &amp; Fils se réserve le droit de modifier ses CGV à tout moment ;
            les ventes sont régies par les CGV en vigueur au moment de la validation de la commande.
        </p>

        <h2>I. Vendeur</h2>

        <p>
            <strong>GFA Bernard Solane &amp; Fils</strong><br>
            Château Crabitan Bellevue – 33410 Sainte-Croix-du-Mont – France<br>
            Tél. : <a href="tel:+33556620153">05 56 62 01 53</a><br>
            Email : <a href="mailto:crabitan.bellevue@orange.fr">crabitan.bellevue@orange.fr</a>
        </p>
        <p>
            Nos produits sont réservés aux particuliers majeurs (18 ans et plus).
            En validant une commande, vous déclarez avoir l'âge légal pour acheter des boissons alcoolisées
            dans votre pays de résidence.
            GFA Bernard Solane &amp; Fils n'a pas vocation à vendre à des professionnels via son site ;
            pour toute demande professionnelle, merci de nous contacter directement.
        </p>

        <h2>II. Prix</h2>

        <p>
            Les prix sont indiqués en euros toutes taxes comprises (TTC), hors frais de livraison.
            GFA Bernard Solane &amp; Fils se réserve le droit de modifier ses prix à tout moment ;
            les produits sont facturés sur la base des tarifs en vigueur au moment de la validation de votre commande.
        </p>
        <p>
            Les produits demeurent la propriété de GFA Bernard Solane &amp; Fils jusqu'au complet règlement du prix.
            Les risques de perte ou d'endommagement sont transférés à l'acheteur dès la prise de possession physique
            des produits commandés.
        </p>
        <p>
            <strong>Zone de livraison :</strong> la livraison est assurée exclusivement en <strong>France hexagonale</strong>
            (hors Corse, îles côtières et collectivités d'outre-mer).
            Pour toute expédition vers une autre zone géographique, merci de nous contacter directement
            afin d'étudier les modalités et les coûts associés.
        </p>

        <h2>III. Commande</h2>

        <p>
            Vous pouvez passer commande sur <a href="https://www.crabitanbellevue.fr">crabitanbellevue.fr</a>.
            Les informations contractuelles sont présentées en français et font l'objet d'une confirmation
            au plus tard au moment de la validation de votre commande.
        </p>
        <p>
            En validant votre commande, vous déclarez avoir pris connaissance et accepté les présentes CGV.
            Un récapitulatif de commande est disponible dans votre espace client rubrique « Mes commandes »,
            et un email de confirmation vous est adressé.
        </p>
        <p>
            GFA Bernard Solane &amp; Fils se réserve le droit de refuser les commandes excédant les stocks disponibles.
            En cas d'indisponibilité d'un produit après votre commande, vous en serez informé par email ;
            la commande sera annulée et vous serez intégralement remboursé dans les meilleurs délais.
        </p>

        <h3>Annulation de commande</h3>
        <p>
            La possibilité d'annuler une commande dépend de son stade de traitement :
        </p>
        <ul>
            <li><strong>Avant paiement :</strong> annulation libre depuis votre espace client, rubrique « Mes commandes ».</li>
            <li><strong>Après paiement, avant mise en préparation :</strong> contactez-nous sans délai par téléphone ou email ;
                si la commande n'a pas encore été prise en charge par nos équipes, nous procéderons à l'annulation
                et au remboursement intégral.</li>
            <li><strong>En cours de préparation ou après expédition :</strong> l'annulation n'est plus possible.
                Le droit légal de rétractation s'applique <strong>à compter de la réception</strong> des produits
                (voir section VI) ; les frais d'expédition aller et de retour restent à votre charge.</li>
            <li><strong>Produit ouvert :</strong> ni annulation ni rétractation n'est possible pour des raisons
                d'hygiène et de sécurité alimentaire (art. L. 221-28 du Code de la consommation).</li>
        </ul>

        <h2>IV. Paiement</h2>

        <p>La validation de votre commande implique l'obligation de payer le prix indiqué. Nous acceptons :</p>
        <ul>
            <li><strong>Carte bancaire</strong> (Visa, Mastercard, CB) — paiement sécurisé via Crédit Agricole</li>
            <li><strong>Virement bancaire</strong> — coordonnées communiquées à la validation de la commande</li>
            <li><strong>Chèque</strong> — à l'ordre de GFA Bernard Solane &amp; Fils, à envoyer à l'adresse du Château</li>
        </ul>
        <p>
            Votre commande est traitée à réception du paiement.
            Nous n'enregistrons pas vos coordonnées bancaires ; les paiements par carte sont gérés
            de façon sécurisée par notre partenaire bancaire via un protocole de <strong>chiffrement TLS
            (Transport Layer Security)</strong>. Une transition vers un chiffrement post-quantique (QPC)
            est en cours de déploiement.
        </p>

        <h2>V. Livraison</h2>

        <p>
            Les produits sont livrés à l'adresse indiquée lors de votre commande,
            dans un délai moyen de 14 jours à compter de la validation de la commande et de l'encaissement.
        </p>
        <p>
            Nos produits (vins en bouteilles) nécessitent le recours à un transporteur spécialisé pour
            le transport de marchandises fragiles et de boissons alcoolisées.
            Ce transporteur prendra contact avec vous pour convenir d'un rendez-vous de livraison,
            dans les 30 jours suivant la validation de votre commande.
            GFA Bernard Solane &amp; Fils ne peut être tenu responsable d'un retard lié à l'indisponibilité
            répétée du client après plusieurs propositions du transporteur.
        </p>
        <p>
            En cas de retard, nous vous en informons par email.
            Conformément à l'article L. 216-2 du Code de la consommation, en cas de retard de livraison,
            vous pouvez résoudre le contrat dans les conditions légales en vigueur.
        </p>
        <p>
            Dès la prise de possession physique des produits (par vous ou un tiers désigné),
            les risques de perte ou d'endommagement vous sont transférés.
            Vous devez consigner par écrit toute réserve sur l'état du colis directement sur le bon de livraison
            du transporteur et nous en informer dans les meilleurs délais.
        </p>

        <h2>VI. Droit de rétractation</h2>

        <h3>1. Délai et conditions</h3>
        <p>
            Conformément aux articles L. 221-18 et suivants du Code de la consommation,
            vous disposez d'un délai de <strong>14 jours</strong> à compter de la réception de vos produits
            pour exercer votre droit de rétractation, sans avoir à justifier de motifs ni à payer de pénalité.
        </p>
        <p>
            Après notification de votre décision de rétractation dans ce délai de 14 jours,
            vous disposez d'un nouveau délai de 14 jours pour retourner le ou les produits.
            En cas de commande contenant plusieurs produits, le délai court à compter de la réception du dernier produit.
        </p>

        <h3>2. Modalités d'exercice</h3>
        <p>
            Pour exercer votre droit de rétractation, adressez-nous une déclaration sans ambiguïté
            (courrier postal ou email) à :
        </p>
        <p>
            GFA Bernard Solane &amp; Fils – Château Crabitan Bellevue – 33410 Sainte-Croix-du-Mont – France<br>
            Email : <a href="mailto:crabitan.bellevue@orange.fr">crabitan.bellevue@orange.fr</a>
        </p>
        <p>
            Les produits retournés doivent l'être dans leur état d'origine et complets
            (emballage intact, accessoires), permettant leur recommercialisation à l'état neuf.
            Seul le prix des produits sera remboursé ; les frais d'envoi aller et les frais de retour restent à votre charge.
            Nous vous communiquons sur demande les coordonnées d'un transporteur habilité pour ce type de marchandise.
        </p>

        <h3>3. Remboursement</h3>
        <p>
            GFA Bernard Solane &amp; Fils procédera au remboursement du montant des produits
            au plus tard dans les <strong>14 jours</strong> à compter de la réception de votre décision,
            selon le même moyen de paiement que celui utilisé lors de la commande.
            Ce délai peut être différé jusqu'à récupération effective du produit ou jusqu'à fourniture
            de la preuve de son expédition, la date retenue étant celle du premier de ces faits.
        </p>

        <h3>4. Produits exclus du droit de rétractation</h3>
        <p>
            Conformément à l'article L. 221-28 du Code de la consommation, le droit de rétractation
            ne peut être exercé pour :
        </p>
        <ul>
            <li>Les biens descellés (bouteilles ouvertes) qui ne peuvent être renvoyés pour des raisons
                d'hygiène ou de sécurité alimentaire ;</li>
            <li>Les coffrets-cadeaux personnalisés (voir conditions spécifiques sur la fiche du produit concerné).</li>
        </ul>

        <h2>VII. Garanties légales</h2>

        <p>
            Nos vins font l'objet d'un suivi qualitatif rigoureux avant expédition.
            En tant que vendeur, nous sommes tenus de deux garanties légales distinctes.
        </p>

        <h3>1. Garantie contre les vices cachés</h3>
        <p>
            Conformément aux articles 1641 et suivants du Code civil, nous garantissons nos vins contre
            tout vice caché les rendant impropres à la consommation ou en diminuant si gravement l'usage
            que vous ne les aurait pas achetés, ou n'en aurait donné qu'un moindre prix, si vous l'aviez connu.
        </p>
        <p>
            Sont notamment concernés les défauts indécelables à la livraison et liés au processus de vinification
            ou de conditionnement (bouchonnage défectueux, oxydation prématurée au bouchage, refermentation anormale…).
        </p>
        <p>
            L'action doit être intentée dans un délai de <strong>2 ans à compter de la découverte du vice</strong>
            (art. 1648 du Code civil). Ce délai est d'ordre public et ne peut être réduit contractuellement
            pour les consommateurs (art. 1649 du Code civil).
            Vous pouvez alors choisir entre la résolution de la vente (remboursement intégral) ou
            une réduction du prix (art. 1644 du Code civil).
        </p>
        <p>
            <strong>Note pratique :</strong> le vin étant un bien périssable, nous vous recommandons vivement
            de nous signaler tout défaut dès son constatation, lors de l'ouverture ou de la dégustation.
            Plus le délai entre l'achat et la découverte est long, plus il peut être difficile d'établir
            l'origine du défaut — fabrication ou conditions de conservation — et donc de faire droit à votre demande.
            Pour faciliter l'instruction, conservez la bouteille incriminée, le bouchon et l'étiquette,
            et contactez-nous avec une description précise du défaut constaté.
        </p>

        <h3>2. Garantie légale de conformité</h3>
        <p>
            Conformément aux articles L. 217-4 et suivants du Code de la consommation, nous répondons
            des défauts de conformité objectifs à la livraison : produit ne correspondant pas à la description
            (millésime, appellation, référence), conditionnement endommagé au départ, quantité incorrecte.
        </p>
        <p>
            Cette garantie couvre une période de <strong>2 ans</strong> à compter de la date de livraison.
            En qualité de bien de consommation à durée de vie limitée, la période de présomption d'antériorité
            du défaut est de <strong>6 mois</strong> pour nos produits.
            Passé ce délai, il vous appartient d'établir que le défaut existait au moment de la livraison.
        </p>

        <h3>3. Assistance produit</h3>
        <p>
            Notre équipe reste disponible pour vous conseiller sur la conservation, le service et
            la dégustation de nos vins. Composez le <strong><a href="tel:+33556620153">05 56 62 01 53</a></strong>
            pendant nos horaires d'ouverture (voir section X).
        </p>

        <h2>VIII. Propriété intellectuelle</h2>

        <p>
            La marque <strong>Château Crabitan Bellevue</strong> est une marque déposée par GFA Bernard Solane &amp; Fils.
            Les dénominations sociales, marques et signes distinctifs reproduits sur ce site sont protégés
            au titre du droit des marques.
            Toute reproduction ou représentation, totale ou partielle, est strictement interdite
            sans autorisation écrite préalable du titulaire.
        </p>
        <p>
            Les photographies et visuels sont communiqués à titre illustratif.
            Reportez-vous au descriptif de chaque produit pour ses caractéristiques précises
            (millésime, appellation, degré alcoolique, volume).
        </p>

        <h2>IX. Responsabilité</h2>

        <p>
            Les produits proposés sont conformes à la législation française et européenne en vigueur.
            La responsabilité de GFA Bernard Solane &amp; Fils ne saurait être engagée en cas de
            non-respect de la législation du pays de livraison du client.
            Il vous appartient de vérifier auprès des autorités locales les conditions d'importation
            des boissons alcoolisées dans votre pays.
        </p>
        <p>
            GFA Bernard Solane &amp; Fils ne saurait être tenu responsable des dommages résultant
            d'une mauvaise conservation (température, luminosité, position des bouteilles)
            ou d'une consommation inappropriée de ses produits.
        </p>
        <p>
            L'abus d'alcool est dangereux pour la santé — à consommer avec modération.
            La vente d'alcool est interdite aux mineurs (art. L. 3342-1 du Code de la santé publique).
        </p>

        <h2>X. Service clientèle</h2>

        <p>
            Pour toute information, question ou réclamation, notre équipe est à votre disposition :
        </p>
        <ul>
            <li><strong>Téléphone :</strong> <a href="tel:+33556620153">05 56 62 01 53</a></li>
            <li><strong>Email :</strong> <a href="mailto:crabitan.bellevue@orange.fr">crabitan.bellevue@orange.fr</a></li>
        </ul>
        <p><strong>Horaires d'ouverture :</strong></p>
        <ul>
            <li>Lundi au samedi : 09h00 – 18h00</li>
            <li>Dimanche : 09h00 – 12h00</li>
        </ul>
        <p>
            Pour le suivi de commande, l'exercice du droit de rétractation ou la mise en œuvre
            d'une garantie, vous pouvez également accéder à votre espace client
            rubrique « Mes commandes » à tout moment.
        </p>

        <h2>XI. Médiation et litiges</h2>

        <p>
            Le présent contrat est soumis au droit français ; les tribunaux français sont seuls compétents
            en cas de litige, sauf disposition légale contraire applicable au consommateur.
        </p>
        <p>
            <strong>Médiation :</strong> en cas de litige non résolu amiablement avec notre service clientèle,
            vous pouvez recourir gratuitement à la médiation de la consommation,
            conformément aux articles L. 611-1 et suivants du Code de la consommation.
            Pour plus d'informations : <a href="https://www.economie.gouv.fr/mediation-conso" target="_blank" rel="noopener noreferrer">economie.gouv.fr/mediation-conso</a>.
        </p>
        <p>
            <strong>Règlement en ligne des litiges :</strong> conformément à l'article 14 du Règlement (UE) n° 524/2013,
            la Commission européenne met à disposition une plateforme de règlement en ligne des litiges (RLL) :
            <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener noreferrer">ec.europa.eu/consumers/odr</a>.
        </p>

        <h2>XII. Données personnelles</h2>

        <p>
            Le traitement de vos données personnelles dans le cadre de vos achats est décrit dans notre
            <a href="/<?= htmlspecialchars($navLang) ?>/politique-de-confidentialite">Politique de confidentialité</a>.
        </p>
        <p>
            Vos données sont nécessaires à la gestion de votre commande et peuvent être transmises
            aux prestataires participant à son exécution (transporteur, établissement bancaire).
            Conformément au RGPD, vous disposez d'un droit d'accès, de rectification, d'effacement
            et d'opposition sur vos données.
            Pour exercer ces droits, contactez-nous à
            <a href="mailto:crabitan.bellevue@orange.fr">crabitan.bellevue@orange.fr</a>
            ou par courrier à l'adresse du Château.
        </p>

<?php else : ?>

        <p class="legal-update">
            <em>Last updated: April 3, 2026</em>
        </p>

        <p>
            These Terms and Conditions of Sale (hereinafter "T&amp;Cs") govern all product sales made by
            <strong>GFA Bernard Solane &amp; Fils</strong>, owner of Château Crabitan Bellevue,
            through the website <strong>crabitanbellevue.fr</strong>.
        </p>
        <p>
            Any purchase implies unreserved acceptance of these T&amp;Cs.
            GFA Bernard Solane &amp; Fils reserves the right to amend these T&amp;Cs at any time;
            sales are governed by the T&amp;Cs in force at the time of order confirmation.
        </p>

        <h2>I. Seller</h2>

        <p>
            <strong>GFA Bernard Solane &amp; Fils</strong><br>
            Château Crabitan Bellevue – 33410 Sainte-Croix-du-Mont – France<br>
            Phone: <a href="tel:+33556620153">+33 5 56 62 01 53</a><br>
            Email: <a href="mailto:crabitan.bellevue@orange.fr">crabitan.bellevue@orange.fr</a>
        </p>
        <p>
            Our products are reserved for adults of legal drinking age (18 years or older).
            By placing an order, you declare that you are of legal age to purchase alcoholic beverages
            in your country of residence.
            GFA Bernard Solane &amp; Fils does not sell to professionals through this website;
            for trade enquiries, please contact us directly.
        </p>

        <h2>II. Prices</h2>

        <p>
            Prices are stated in euros inclusive of all taxes (VAT), excluding shipping costs.
            GFA Bernard Solane &amp; Fils reserves the right to modify prices at any time;
            products are invoiced at the price in force at the time of order confirmation.
        </p>
        <p>
            Products remain the property of GFA Bernard Solane &amp; Fils until full payment is received.
            Risk of loss or damage transfers to you upon physical receipt of the goods.
        </p>
        <p>
            <strong>Delivery area:</strong> deliveries are made exclusively within <strong>mainland France</strong>
            (excluding Corsica, coastal islands and overseas territories).
            For shipments outside this area, please contact us directly to discuss arrangements and costs.
        </p>

        <h2>III. Ordering</h2>

        <p>
            You may place orders at <a href="https://www.crabitanbellevue.fr">crabitanbellevue.fr</a>.
            By confirming your order, you acknowledge that you have read and accepted these T&amp;Cs.
            An order confirmation email will be sent to you, and your orders are accessible
            in your customer account under "My Orders".
        </p>
        <p>
            GFA Bernard Solane &amp; Fils reserves the right to refuse orders that exceed available stock.
            In the event of unavailability after your order is placed, you will be notified by email
            and fully refunded.
        </p>

        <h3>Order cancellation</h3>
        <p>Whether cancellation is possible depends on the stage of processing:</p>
        <ul>
            <li><strong>Before payment:</strong> cancel freely from your customer account under "My Orders".</li>
            <li><strong>After payment, before preparation begins:</strong> contact us immediately by phone or email;
                if your order has not yet been picked up by our team, we will cancel it and refund you in full.</li>
            <li><strong>Once in preparation or after dispatch:</strong> cancellation is no longer possible.
                The legal right of withdrawal applies <strong>from the date of receipt</strong> of the goods
                (see Section VI); outbound and return shipping costs remain your responsibility.</li>
            <li><strong>Once opened:</strong> neither cancellation nor withdrawal is possible for food safety
                and hygiene reasons (Article L. 221-28 of the French Consumer Code).</li>
        </ul>

        <h2>IV. Payment</h2>

        <p>Confirming your order implies an obligation to pay the stated price. We accept:</p>
        <ul>
            <li><strong>Credit/debit card</strong> (Visa, Mastercard, CB) — secure payment via Crédit Agricole</li>
            <li><strong>Bank transfer</strong> — details provided upon order confirmation</li>
            <li><strong>Cheque</strong> — payable to GFA Bernard Solane &amp; Fils, to be sent to the Château</li>
        </ul>
        <p>
            Your order is processed upon receipt of payment.
            We do not store your card details; card payments are processed securely
            by our banking partner using <strong>TLS encryption (Transport Layer Security)</strong>.
            A transition to post-quantum cryptography (QPC) is currently being deployed.
        </p>

        <h2>V. Delivery</h2>

        <p>
            Products are delivered to the address provided during ordering,
            within an average of 14 days from order and payment confirmation.
        </p>
        <p>
            Our products (bottled wines) require a specialist carrier handling fragile goods
            and alcoholic beverages. The carrier will contact you to schedule a delivery appointment
            within 30 days of order confirmation.
            GFA Bernard Solane &amp; Fils cannot be held responsible for delivery delays
            due to the customer's repeated unavailability after multiple appointment proposals.
        </p>
        <p>
            Risk of loss or damage transfers to you upon physical receipt of the goods.
            Any damage or discrepancy must be recorded in writing on the carrier's delivery note
            and reported to us as soon as possible.
        </p>

        <h2>VI. Right of Withdrawal</h2>

        <h3>1. Period and conditions</h3>
        <p>
            In accordance with applicable consumer protection law, you have <strong>14 days</strong>
            from receipt of your products to exercise your right of withdrawal, without giving reasons
            and without penalty.
        </p>
        <p>
            After notifying us of your decision within this 14-day period, you have a further
            14 days to return the product(s).
            For orders containing multiple products, the period runs from receipt of the last item.
        </p>

        <h3>2. How to exercise your right</h3>
        <p>
            Send an unambiguous declaration (by post or email) to:<br>
            GFA Bernard Solane &amp; Fils – Château Crabitan Bellevue – 33410 Sainte-Croix-du-Mont – France<br>
            Email: <a href="mailto:crabitan.bellevue@orange.fr">crabitan.bellevue@orange.fr</a>
        </p>
        <p>
            Returned products must be in their original, sealed condition and complete with all packaging,
            to allow resale as new. Only the product price will be refunded; outbound and return shipping costs
            remain your responsibility. We can provide contact details for a specialist carrier on request.
        </p>

        <h3>3. Refund</h3>
        <p>
            GFA Bernard Solane &amp; Fils will refund the product price within <strong>14 days</strong>
            of receiving your withdrawal notification, using the same payment method as your original order.
            The refund may be deferred until the product is received or proof of dispatch is provided,
            whichever comes first.
        </p>

        <h3>4. Exceptions to the right of withdrawal</h3>
        <p>
            The right of withdrawal does not apply to:
        </p>
        <ul>
            <li>Goods unsealed (bottles opened) by the customer that cannot be returned for food safety or hygiene reasons;</li>
            <li>Personalised gift sets (see specific conditions on the relevant product page).</li>
        </ul>

        <h2>VII. Legal Warranties</h2>

        <p>
            Our wines undergo rigorous quality control before dispatch.
            As the seller, we are bound by two distinct legal warranties.
        </p>

        <h3>1. Warranty against hidden defects</h3>
        <p>
            Under Articles 1641 et seq. of the French Civil Code, we warrant our wines against hidden
            defects that render them unfit for consumption or substantially reduce their use value
            to the point where you would not have purchased them, or would have paid a lower price, had you known.
        </p>
        <p>
            This covers defects undetectable at delivery and attributable to the winemaking or bottling process
            (faulty corking, premature oxidation at bottling, abnormal refermentation, etc.).
        </p>
        <p>
            You must bring your claim within <strong>2 years of discovering the defect</strong>
            (Article 1648 of the French Civil Code). This period is mandatory and cannot be contractually
            reduced for consumers (Article 1649 of the Civil Code).
            You may then choose between rescission of the sale (full refund) or a reduction in price
            (Article 1644 of the Civil Code).
        </p>
        <p>
            <strong>Practical note:</strong> as wine is a perishable product, we strongly encourage you
            to report any defect as soon as it is discovered, at the time of opening or tasting.
            The longer the interval between purchase and discovery, the more difficult it may be to
            establish whether the defect originated in production or resulted from storage conditions —
            and the harder it will be to uphold your claim.
            To support your request, please keep the bottle (including the cork and label) and contact us
            with a precise description of the defect observed.
        </p>

        <h3>2. Statutory conformity warranty</h3>
        <p>
            Under Articles L. 217-4 et seq. of the French Consumer Code, we are liable for objective
            non-conformities at the time of delivery: product not matching the description
            (vintage, appellation, reference), damaged packaging at dispatch, or incorrect quantity.
        </p>
        <p>
            This warranty covers a period of <strong>2 years</strong> from delivery.
            As a consumable product with a limited shelf life, the presumption period for pre-existing
            defects is <strong>6 months</strong> for our products.
            After this period, you must demonstrate that the defect existed at the time of delivery.
        </p>

        <h3>3. Product support</h3>
        <p>
            Our team is available to advise you on storing, serving and enjoying our wines.
            Call us on <strong><a href="tel:+33556620153">+33 5 56 62 01 53</a></strong>
            during our opening hours (see Section X).
        </p>

        <h2>VIII. Intellectual Property</h2>

        <p>
            The <strong>Château Crabitan Bellevue</strong> trademark is registered by GFA Bernard Solane &amp; Fils.
            All trademarks, trade names and distinctive signs on this site are protected.
            Any reproduction or representation without prior written authorisation is strictly prohibited.
        </p>
        <p>
            Photographs and visuals are provided for illustrative purposes only.
            Please refer to each product description for precise characteristics
            (vintage, appellation, alcohol content, volume).
        </p>

        <h2>IX. Liability</h2>

        <p>
            Products comply with French and European law. GFA Bernard Solane &amp; Fils cannot be held
            responsible for non-compliance with the laws of the customer's country of delivery.
            It is your responsibility to verify local import conditions for alcoholic beverages.
        </p>
        <p>
            GFA Bernard Solane &amp; Fils cannot be held liable for damage resulting from improper storage
            (temperature, light exposure, bottle position) or inappropriate consumption of its products.
        </p>
        <p>
            Alcohol abuse is dangerous for your health — to be consumed in moderation.
            The sale of alcohol to minors is prohibited.
        </p>

        <h2>X. Customer Service</h2>

        <p>For any enquiry, question or complaint, our team is available:</p>
        <ul>
            <li><strong>Phone:</strong> <a href="tel:+33556620153">+33 5 56 62 01 53</a></li>
            <li><strong>Email:</strong> <a href="mailto:crabitan.bellevue@orange.fr">crabitan.bellevue@orange.fr</a></li>
        </ul>
        <p><strong>Opening hours:</strong></p>
        <ul>
            <li>Monday to Saturday: 09:00 – 18:00 (CET/CEST)</li>
            <li>Sunday: 09:00 – 12:00 (CET/CEST)</li>
        </ul>
        <p>
            For order tracking, exercising your right of withdrawal or making a warranty claim,
            you can also access your customer account under "My Orders" at any time.
        </p>

        <h2>XI. Dispute Resolution</h2>

        <p>
            These T&amp;Cs are governed by French law; French courts have sole jurisdiction in the event of a dispute,
            unless mandatory consumer protection provisions of your country apply.
        </p>
        <p>
            <strong>Mediation:</strong> if a dispute cannot be resolved amicably with our customer service team,
            you may use the EU Online Dispute Resolution platform:
            <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener noreferrer">ec.europa.eu/consumers/odr</a>.
        </p>

        <h2>XII. Personal Data</h2>

        <p>
            How we handle your personal data in connection with your purchases is described in our
            <a href="/<?= htmlspecialchars($navLang) ?>/politique-de-confidentialite">Privacy Policy</a>.
        </p>
        <p>
            You have the right to access, rectify, erase and object to processing of your personal data
            by contacting us at
            <a href="mailto:crabitan.bellevue@orange.fr">crabitan.bellevue@orange.fr</a>
            or by post to the Château address.
        </p>

<?php endif; ?>

<?php require_once SRC_PATH . '/View/partials/legal-close.php'; ?>
