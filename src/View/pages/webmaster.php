<?php
$pageTitle = __('footer.webmaster');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';

$birth = new \DateTime('1997-04-15');
$age   = (new \DateTime())->diff($birth)->y;
?>

<main class="page-webmaster" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <span class="home-section__tag"><?= htmlspecialchars(__('footer.made_by')) ?></span>
            <h1 class="home-section__title"><?= htmlspecialchars(__('footer.webmaster')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <section class="cv-section container" aria-label="<?= htmlspecialchars(__('footer.webmaster')) ?>">

        <!-- En-tête CV -->
        <header class="cv-header">
            <div class="cv-header__photo">
                <img src="/assets/images/cv/cv_solane.jpg"
                     alt="Alexandre Solane">
            </div>
            <div class="cv-header__info">
                <h2 class="cv-header__name">Alexandre Solane</h2>
                <p class="cv-header__title"><?= __('webmaster.job_title') ?></p>
                <p class="cv-header__intro"><?= htmlspecialchars(__('webmaster.intro')) ?></p>
                <ul class="cv-header__meta">
                    <li>
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75
                                7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 12
                                6.5a2.5 2.5 0 0 1 0 5z"/>
                        </svg>
                        Auvergne-Rhône-Alpes, France
                    </li>
                    <li>
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7
                                2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12
                                12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2
                                v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
                        </svg>
                        <?= (int) $age ?> <?= htmlspecialchars(__('webmaster.years_old')) ?>
                    </li>
                    <li>
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0
                                1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0
                                0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79
                                v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79
                                M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69
                                a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39
                                9.94v-8.37H5.5v8.37h2.77z"/>
                        </svg>
                        <a href="https://www.linkedin.com/in/alexandre-solane-web/"
                           target="_blank" rel="noopener noreferrer">LinkedIn</a>
                    </li>
                    <li>
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2A10 10 0 0 0 2 12c0 4.42 2.87 8.17 6.84 9.5.5.08.66-.23.66-.5
                                v-1.69c-2.77.6-3.36-1.34-3.36-1.34-.46-1.16-1.11-1.47-1.11-1.47
                                -.91-.62.07-.6.07-.6 1 .07 1.53 1.03 1.53 1.03.87 1.52 2.34 1.07
                                2.91.83.09-.65.35-1.09.63-1.34-2.22-.25-4.55-1.11-4.55-4.92
                                0-1.11.38-2 1.03-2.71-.1-.25-.45-1.29.1-2.64 0 0 .84-.27 2.75 1.02
                                .79-.22 1.65-.33 2.5-.33.85 0 1.71.11 2.5.33 1.91-1.29 2.75-1.02
                                2.75-1.02.55 1.35.2 2.39.1 2.64.65.71 1.03 1.6 1.03 2.71
                                0 3.82-2.34 4.66-4.57 4.91.36.31.69.92.69 1.85V21c0 .27.16.59.67.5
                                C19.14 20.16 22 16.42 22 12A10 10 0 0 0 12 2z"/>
                        </svg>
                        <a href="https://github.com/ApoSkunz"
                           target="_blank" rel="noopener noreferrer">GitHub</a>
                    </li>
                </ul>
            </div>
        </header>

        <!-- Expérience professionnelle -->
        <section class="cv-block" aria-labelledby="cv-exp-title">
            <h3 id="cv-exp-title" class="cv-block__title">
                <?= htmlspecialchars(__('webmaster.exp_title')) ?>
            </h3>

            <ol class="cv-timeline">

                <li class="cv-timeline__item">
                    <div class="cv-timeline__dot"></div>
                    <div class="cv-timeline__content">
                        <div class="cv-timeline__header">
                            <span class="cv-timeline__company">EDF</span>
                            <span class="cv-timeline__badge">CDI</span>
                        </div>
                        <p class="cv-timeline__role">Ingénieur DevSecOps &amp; SRE</p>
                        <p class="cv-timeline__period">jan. 2025 → présent · Lyon, hybride</p>
                        <ul class="cv-timeline__tasks">
                            <li>Lorem ipsum dolor sit amet, consectetur adipiscing elit</li>
                            <li>Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua</li>
                            <li>Ut enim ad minim veniam, quis nostrud exercitation ullamco</li>
                            <li>Duis aute irure dolor in reprehenderit in voluptate velit esse</li>
                        </ul>
                        <p class="cv-timeline__sub-role">
                            <em>Intégrateur d'applications — DevSecOps / SRE</em>
                            · nov. 2023 → déc. 2024 · 1 an 1 mois
                        </p>
                        <ul class="cv-timeline__tasks">
                            <li>Élaboration d'une stratégie DevSecOps industrielle
                                et roadmap sur 16 applications CAO 2D / 3D / 4D</li>
                            <li>Mise en place CI/CD avec workflow design :
                                Security Gates SAST / SCA / SonarQube, Quality Gates</li>
                            <li>Expérimentation DORA metrics pour mesurer la performance DevOps</li>
                            <li>Architecture commune pour les Déploiements Automatisés
                                (Ansible, IaC)</li>
                            <li>SRE : surveillance continue, alerting, résilience
                                from scratch, zéro obsolescence</li>
                            <li>Gestion de projet Kanban : affinage tickets DevSecOps,
                                animation rétros</li>
                        </ul>
                    </div>
                </li>

                <li class="cv-timeline__item">
                    <div class="cv-timeline__dot"></div>
                    <div class="cv-timeline__content">
                        <div class="cv-timeline__header">
                            <span class="cv-timeline__company">Capgemini</span>
                            <span class="cv-timeline__badge">CDI</span>
                        </div>
                        <p class="cv-timeline__role">Ingénieur DevOps — Team Leader System Team SAFe</p>
                        <p class="cv-timeline__period">août 2021 → nov. 2023 · 2 ans 4 mois · Lyon</p>
                        <ul class="cv-timeline__tasks">
                            <li>Team Leader DevOps coordonnant 3 ingénieurs sur un Train SAFe</li>
                            <li>Déploiement automatisé : GitLab CI, Jenkins, Ansible</li>
                            <li>Surveillance automatisée : monitoring, observability, alerting</li>
                            <li>Support technique multi-middleware et intégration continue</li>
                        </ul>
                        <p class="cv-timeline__sub-role">
                            <em>Stagiaire DevOps</em> · févr.–août 2021 · 7 mois
                        </p>
                        <ul class="cv-timeline__tasks">
                            <li>MCO d'une plateforme d'Intégration Continue (GitLab, Jenkins)
                                et développement de shared libraries Jenkins CI/CD</li>
                            <li>POC Asqatasun / Tanaguru (conformité RGAA &amp; GreenIT),
                                politique de rétention des sauvegardes et des artifacts</li>
                            <li>Stage axé CI/CD, GreenIT automatisé, monitoring, alerting</li>
                            <li>GitLab, Jenkins, SonarQube, ELK, Python, Shell</li>
                        </ul>
                    </div>
                </li>

                <li class="cv-timeline__item">
                    <div class="cv-timeline__dot"></div>
                    <div class="cv-timeline__content">
                        <div class="cv-timeline__header">
                            <span class="cv-timeline__company">G.F.A Bernard Solane &amp; Fils</span>
                            <span class="cv-timeline__badge cv-timeline__badge--stage">Stage</span>
                        </div>
                        <p class="cv-timeline__role">Stagiaire Développeur Full Stack</p>
                        <p class="cv-timeline__period">juil.–août 2020 · Sainte-Croix-du-Mont</p>
                        <ul class="cv-timeline__tasks">
                            <li>Refonte du site vitrine en site e-commerce
                                (PHP, MySQL, JS, HTML/CSS)</li>
                            <li>Architecture MVC, API REST interne, paiement Crédit Agricole</li>
                            <li>~50 pages utilisateurs et administrateurs, sécurité OWASP Top 10</li>
                            <li>MCO du site assurée depuis lors</li>
                        </ul>
                        <p class="cv-timeline__sub-role">
                            <em>Stagiaire Développeur Web</em> · jan.–févr. 2019
                        </p>
                        <ul class="cv-timeline__tasks">
                            <li>Conception d'un site vitrine HTML / CSS / JavaScript</li>
                        </ul>
                    </div>
                </li>

            </ol>
        </section>

        <!-- Formation -->
        <section class="cv-block" aria-labelledby="cv-edu-title">
            <h3 id="cv-edu-title" class="cv-block__title">
                <?= htmlspecialchars(__('webmaster.edu_title')) ?>
            </h3>

            <ol class="cv-timeline">

                <li class="cv-timeline__item">
                    <div class="cv-timeline__dot"></div>
                    <div class="cv-timeline__content">
                        <div class="cv-timeline__header">
                            <span class="cv-timeline__company">Télécom Saint-Étienne</span>
                            <span class="cv-timeline__badge cv-timeline__badge--stage">
                                Diplôme d'ingénieur
                            </span>
                        </div>
                        <p class="cv-timeline__role">
                            Informatique &amp; Télécommunications — Réseaux, systèmes,
                            développement logiciel
                        </p>
                        <p class="cv-timeline__period">2018 – 2021 · Saint-Étienne</p>
                        <ul class="cv-timeline__tasks">
                            <li>Spécialités : Java Académie, High Performance Programming</li>
                            <li>TOEIC 830</li>
                        </ul>
                    </div>
                </li>

                <li class="cv-timeline__item">
                    <div class="cv-timeline__dot"></div>
                    <div class="cv-timeline__content">
                        <div class="cv-timeline__header">
                            <span class="cv-timeline__company">Classes Préparatoires CPGE PC</span>
                            <span class="cv-timeline__badge cv-timeline__badge--stage">
                                Prépa
                            </span>
                        </div>
                        <p class="cv-timeline__role">
                            Filière Physique-Chimie — Mathématiques, physique, sciences de l'ingénieur
                        </p>
                        <p class="cv-timeline__period">2015 – 2018</p>
                    </div>
                </li>

                <li class="cv-timeline__item">
                    <div class="cv-timeline__dot"></div>
                    <div class="cv-timeline__content">
                        <div class="cv-timeline__header">
                            <span class="cv-timeline__company">Baccalauréat S</span>
                            <span class="cv-timeline__badge cv-timeline__badge--stage">BAC</span>
                        </div>
                        <p class="cv-timeline__role">Série scientifique</p>
                        <p class="cv-timeline__period">2015</p>
                    </div>
                </li>

                <li class="cv-timeline__item">
                    <div class="cv-timeline__dot"></div>
                    <div class="cv-timeline__content">
                        <div class="cv-timeline__header">
                            <span class="cv-timeline__company">OpenClassrooms</span>
                            <span class="cv-timeline__badge cv-timeline__badge--stage">
                                Auto-formation
                            </span>
                        </div>
                        <p class="cv-timeline__role">
                            Nombreux cours suivis en continu : développement web, DevOps,
                            sécurité, gestion de projet
                        </p>
                        <p class="cv-timeline__period">2015 → présent</p>
                    </div>
                </li>

            </ol>
        </section>

        <!-- Compétences techniques -->
        <section class="cv-block" aria-labelledby="cv-skills-title">
            <h3 id="cv-skills-title" class="cv-block__title">
                <?= htmlspecialchars(__('webmaster.skills_title')) ?>
            </h3>
            <div class="cv-skills">
                <div class="cv-skills__group">
                    <h4 class="cv-skills__cat">DevSecOps / SRE</h4>
                    <ul class="cv-skills__tags">
                        <li>GitLab CI</li><li>Jenkins</li><li>Ansible</li>
                        <li>SonarQube</li><li>SAST / SCA</li><li>IaC</li>
                        <li>ELK</li><li>Kanban / SAFe</li>
                    </ul>
                </div>
                <div class="cv-skills__group">
                    <h4 class="cv-skills__cat">Développement</h4>
                    <ul class="cv-skills__tags">
                        <li>PHP</li><li>MySQL</li><li>JavaScript</li>
                        <li>HTML5</li><li>CSS3 / SCSS</li>
                        <li>Python</li><li>Shell</li><li>Java</li>
                    </ul>
                </div>
                <div class="cv-skills__group">
                    <h4 class="cv-skills__cat">Systèmes</h4>
                    <ul class="cv-skills__tags">
                        <li>Linux</li><li>AIX</li><li>Windows Server</li>
                    </ul>
                </div>
                <div class="cv-skills__group">
                    <h4 class="cv-skills__cat">Middlewares &amp; BDD</h4>
                    <ul class="cv-skills__tags">
                        <li>Apache</li><li>JBoss</li><li>Tomcat</li>
                        <li>PostgreSQL</li><li>OpenSSL</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Me contacter -->
        <section class="cv-contact" aria-labelledby="cv-contact-title">
            <h3 id="cv-contact-title" class="cv-contact__title">
                <?= htmlspecialchars(__('webmaster.contact_title')) ?>
            </h3>
            <p class="cv-contact__text">
                <?= htmlspecialchars(__('webmaster.contact_text')) ?>
            </p>
            <a href="https://www.linkedin.com/in/alexandre-solane-web/"
               target="_blank" rel="noopener noreferrer"
               class="btn btn--gold">
                <?= htmlspecialchars(__('webmaster.contact_btn')) ?>
            </a>
        </section>

    </section>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
