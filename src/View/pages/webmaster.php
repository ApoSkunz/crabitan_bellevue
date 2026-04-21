<?php
$navLang         = $lang ?? (defined('CURRENT_LANG') ? CURRENT_LANG : 'fr');
$pageTitle       = __('footer.webmaster');
$metaDescription = $navLang === 'en'
    ? 'Webmaster information for the Château Crabitan Bellevue website — technical details and contacts.'
    : 'Informations webmaster du site Château Crabitan Bellevue — détails techniques et contacts.';
$appUrl = APP_BASE_URL;
$canonical  = $appUrl . '/' . $navLang . '/webmaster';
$hreflangFr = $appUrl . '/fr/webmaster';
$hreflangEn = $appUrl . '/en/webmaster';
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
                <p class="cv-header__intro">
                    <?= htmlspecialchars(__('webmaster.intro')) ?>
                    <br><?= htmlspecialchars(__('webmaster.intro_edf')) ?>
                    <em class="cv-header__signature">01001000 01100101 01101100 01101100 01101111</em>
                </p>
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
                            <span class="cv-timeline__badge"><?= htmlspecialchars(__('webmaster.badge_cdi')) ?></span>
                        </div>
                        <p class="cv-timeline__period"><?= htmlspecialchars(__('webmaster.edf_total_period')) ?></p>
                        <p class="cv-timeline__sub-role">
                            <em><?= htmlspecialchars(__('webmaster.edf_role')) ?></em>
                            · <?= htmlspecialchars(__('webmaster.edf_period')) ?>
                        </p>
                        <ul class="cv-timeline__tasks">
                            <li><?= htmlspecialchars(__('webmaster.edf_task1')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.edf_task2')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.edf_task3')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.edf_task4')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.edf_task5')) ?></li>
                        </ul>
                        <p class="cv-timeline__sub-role">
                            <em><?= htmlspecialchars(__('webmaster.edf_sub_role')) ?></em>
                            · <?= htmlspecialchars(__('webmaster.edf_sub_period')) ?>
                        </p>
                        <ul class="cv-timeline__tasks">
                            <li><?= htmlspecialchars(__('webmaster.edf_sub_task1')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.edf_sub_task2')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.edf_sub_task3')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.edf_sub_task4')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.edf_sub_task5')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.edf_sub_task6')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.edf_sub_task7')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.edf_sub_task8')) ?></li>
                        </ul>
                    </div>
                </li>

                <li class="cv-timeline__item">
                    <div class="cv-timeline__dot"></div>
                    <div class="cv-timeline__content">
                        <div class="cv-timeline__header">
                            <span class="cv-timeline__company">Capgemini</span>
                            <span class="cv-timeline__badge"><?= htmlspecialchars(__('webmaster.badge_cdi')) ?></span>
                        </div>
                        <p class="cv-timeline__role"><?= htmlspecialchars(__('webmaster.capgemini_role')) ?></p>
                        <p class="cv-timeline__period"><?= htmlspecialchars(__('webmaster.capgemini_period')) ?></p>
                        <ul class="cv-timeline__tasks">
                            <li><?= htmlspecialchars(__('webmaster.capgemini_task1')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.capgemini_task2')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.capgemini_task3')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.capgemini_task4')) ?></li>
                        </ul>
                        <p class="cv-timeline__sub-role">
                            <em><?= htmlspecialchars(__('webmaster.capgemini_sub_role')) ?></em>
                            · <?= htmlspecialchars(__('webmaster.capgemini_sub_period')) ?>
                        </p>
                        <ul class="cv-timeline__tasks">
                            <li><?= htmlspecialchars(__('webmaster.capgemini_sub_task1')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.capgemini_sub_task2')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.capgemini_sub_task3')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.capgemini_sub_task4')) ?></li>
                        </ul>
                    </div>
                </li>

                <li class="cv-timeline__item">
                    <div class="cv-timeline__dot"></div>
                    <div class="cv-timeline__content">
                        <div class="cv-timeline__header">
                            <span class="cv-timeline__company">G.F.A Bernard Solane &amp; Fils</span>
                            <span class="cv-timeline__badge cv-timeline__badge--stage"><?= htmlspecialchars(__('webmaster.badge_stage')) ?></span>
                        </div>
                        <p class="cv-timeline__role"><?= htmlspecialchars(__('webmaster.gfa_role')) ?></p>
                        <p class="cv-timeline__period"><?= htmlspecialchars(__('webmaster.gfa_period')) ?></p>
                        <ul class="cv-timeline__tasks">
                            <li><?= htmlspecialchars(__('webmaster.gfa_task1')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.gfa_task2')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.gfa_task3')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.gfa_task4')) ?></li>
                        </ul>
                        <p class="cv-timeline__sub-role">
                            <em><?= htmlspecialchars(__('webmaster.gfa_sub_role')) ?></em>
                            · <?= htmlspecialchars(__('webmaster.gfa_sub_period')) ?>
                        </p>
                        <ul class="cv-timeline__tasks">
                            <li><?= htmlspecialchars(__('webmaster.gfa_sub_task1')) ?></li>
                        </ul>
                    </div>
                </li>

            </ol>
        </section>

        <!-- Projets personnels -->
        <section class="cv-block" aria-labelledby="cv-perso-title">
            <h3 id="cv-perso-title" class="cv-block__title">
                <?= htmlspecialchars(__('webmaster.perso_title')) ?>
            </h3>
            <ol class="cv-timeline">
                <li class="cv-timeline__item">
                    <div class="cv-timeline__dot"></div>
                    <div class="cv-timeline__content">
                        <div class="cv-timeline__header">
                            <span class="cv-timeline__company">G.F.A Bernard Solane &amp; Fils — Château Crabitan Bellevue</span>
                            <span class="cv-timeline__badge cv-timeline__badge--stage"><?= htmlspecialchars(__('webmaster.badge_perso')) ?></span>
                        </div>
                        <p class="cv-timeline__role"><?= htmlspecialchars(__('webmaster.perso_gfa_role')) ?></p>
                        <p class="cv-timeline__period"><?= htmlspecialchars(__('webmaster.perso_gfa_period')) ?></p>
                        <ul class="cv-timeline__tasks">
                            <li><?= htmlspecialchars(__('webmaster.perso_gfa_task1')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.perso_gfa_task2')) ?></li>
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
                                <?= htmlspecialchars(__('webmaster.telecom_degree')) ?>
                            </span>
                        </div>
                        <p class="cv-timeline__role"><?= htmlspecialchars(__('webmaster.telecom_role')) ?></p>
                        <p class="cv-timeline__period"><?= htmlspecialchars(__('webmaster.telecom_period')) ?></p>
                        <ul class="cv-timeline__tasks">
                            <li><?= htmlspecialchars(__('webmaster.telecom_task1')) ?></li>
                            <li><?= htmlspecialchars(__('webmaster.telecom_task4')) ?></li>
                        </ul>
                    </div>
                </li>

                <li class="cv-timeline__item">
                    <div class="cv-timeline__dot"></div>
                    <div class="cv-timeline__content">
                        <div class="cv-timeline__header">
                            <span class="cv-timeline__company">Classes Préparatoires CPGE PC</span>
                            <span class="cv-timeline__badge cv-timeline__badge--stage">
                                <?= htmlspecialchars(__('webmaster.cpge_degree')) ?>
                            </span>
                        </div>
                        <p class="cv-timeline__role"><?= htmlspecialchars(__('webmaster.cpge_role')) ?></p>
                        <p class="cv-timeline__period"><?= htmlspecialchars(__('webmaster.cpge_period')) ?></p>
                    </div>
                </li>

                <li class="cv-timeline__item">
                    <div class="cv-timeline__dot"></div>
                    <div class="cv-timeline__content">
                        <div class="cv-timeline__header">
                            <span class="cv-timeline__company">Baccalauréat S</span>
                            <span class="cv-timeline__badge cv-timeline__badge--stage"><?= htmlspecialchars(__('webmaster.bac_degree')) ?></span>
                        </div>
                        <p class="cv-timeline__role"><?= htmlspecialchars(__('webmaster.bac_role')) ?></p>
                        <p class="cv-timeline__period"><?= htmlspecialchars(__('webmaster.bac_period')) ?></p>
                    </div>
                </li>

                <li class="cv-timeline__item">
                    <div class="cv-timeline__dot"></div>
                    <div class="cv-timeline__content">
                        <div class="cv-timeline__header">
                            <span class="cv-timeline__company">OpenClassrooms</span>
                            <span class="cv-timeline__badge cv-timeline__badge--stage">
                                <?= htmlspecialchars(__('webmaster.oc_badge')) ?>
                            </span>
                        </div>
                        <p class="cv-timeline__role"><?= htmlspecialchars(__('webmaster.oc_role')) ?></p>
                        <p class="cv-timeline__period"><?= htmlspecialchars(__('webmaster.oc_period')) ?></p>
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
                        <li>Terraform</li><li>Docker</li><li>SonarQube</li>
                        <li>SAST / SCA / DAST / MAST</li><li>IaC</li>
                        <li>ELK</li><li>Kanban / SAFe</li>
                    </ul>
                </div>
                <div class="cv-skills__group">
                    <h4 class="cv-skills__cat"><?= htmlspecialchars(__('webmaster.skills_dev')) ?></h4>
                    <ul class="cv-skills__tags">
                        <li>PHP</li><li>JavaScript</li>
                        <li>HTML5</li><li>CSS3 / SCSS</li>
                        <li>Python</li><li>Shell</li><li>PowerShell</li><li>Java</li>
                    </ul>
                </div>
                <div class="cv-skills__group">
                    <h4 class="cv-skills__cat"><?= htmlspecialchars(__('webmaster.skills_sys')) ?></h4>
                    <ul class="cv-skills__tags">
                        <li>Linux</li><li>AIX</li><li>Windows Server</li>
                    </ul>
                </div>
                <div class="cv-skills__group">
                    <h4 class="cv-skills__cat"><?= htmlspecialchars(__('webmaster.skills_mw')) ?></h4>
                    <ul class="cv-skills__tags">
                        <li>Apache</li><li>JBoss</li><li>Tomcat</li>
                        <li>MySQL</li><li>PostgreSQL</li><li>Oracle</li><li>OpenSSL</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Langues -->
        <section class="cv-block" aria-labelledby="cv-langs-title">
            <h3 id="cv-langs-title" class="cv-block__title">
                <?= htmlspecialchars(__('webmaster.langs_title')) ?>
            </h3>
            <div class="cv-skills">
                <div class="cv-skills__group">
                    <ul class="cv-skills__tags">
                        <li><?= htmlspecialchars(__('webmaster.lang_fr')) ?></li>
                        <li><?= htmlspecialchars(__('webmaster.lang_en')) ?></li>
                        <li><?= htmlspecialchars(__('webmaster.lang_zh')) ?></li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Centres d'intérêt -->
        <section class="cv-block cv-block--hobbies" aria-labelledby="cv-hobbies-title">
            <h3 id="cv-hobbies-title" class="cv-block__title">
                <?= htmlspecialchars(__('webmaster.hobbies_title')) ?>
            </h3>
            <div class="cv-skills">
                <div class="cv-skills__group">
                    <h4 class="cv-skills__cat"><?= htmlspecialchars(__('webmaster.hobbies_lifestyle')) ?></h4>
                    <ul class="cv-skills__tags">
                        <li><?= htmlspecialchars(__('webmaster.hobbies_gastronomy')) ?></li>
                        <li><?= htmlspecialchars(__('webmaster.hobbies_arch')) ?></li>
                    </ul>
                </div>
                <div class="cv-skills__group">
                    <h4 class="cv-skills__cat"><?= htmlspecialchars(__('webmaster.hobbies_tech')) ?></h4>
                    <ul class="cv-skills__tags">
                        <li><?= htmlspecialchars(__('webmaster.hobbies_it')) ?></li>
                        <li><?= htmlspecialchars(__('webmaster.hobbies_cyber')) ?></li>
                        <li><?= htmlspecialchars(__('webmaster.hobbies_gaming')) ?></li>
                    </ul>
                </div>
                <div class="cv-skills__group">
                    <h4 class="cv-skills__cat"><?= htmlspecialchars(__('webmaster.hobbies_sport')) ?></h4>
                    <ul class="cv-skills__tags">
                        <li><?= htmlspecialchars(__('webmaster.hobbies_rugby')) ?></li>
                        <li><?= htmlspecialchars(__('webmaster.hobbies_hardrock')) ?></li>
                        <li><?= htmlspecialchars(__('webmaster.hobbies_opera')) ?></li>
                        <li><?= htmlspecialchars(__('webmaster.hobbies_rock')) ?></li>
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
