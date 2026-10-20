<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="shortcut icon" type="image/jpg" href="<?php echo esc_url( get_template_directory_uri() ); ?>/images/favicon.jpg"/>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
    <header class="masthead">
        <div class="container d-flex justify-content-between align-items-center">
            <!-- Logo -->
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
                <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/logokams-2.png" title="KAM Servicing AA Approved Garage Logo" class="img-fluid img-responsive" alt="KAM Logo">
            </a>

            <div class="cart-wrapper">
                <p class="mb-0 text-end">
                    <a href="https://kamservicing.com/blank.php" class="cart-link">
                        <span id="cartCount">£0.00</span>
                        <i class="fa-solid fa-cart-shopping"></i>
                    </a>
                </p>
            </div>

        </div>
    </header>
    <nav class="navbar navbar-expand-md navbar-dark pt-0 pb-0" style="background-color:#2691D5;">
        <div class="container">

            <!-- Toggle button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarNavDropdown"
                aria-controls="navbarNavDropdown"
                aria-expanded="false"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navbar links -->
            <div class="collapse navbar-collapse" id="navbarNavDropdown">
                <ul class="navbar-nav me-auto mb-2 mb-md-0">

                    <li class="nav-item">
                        <a class="nav-link<?php echo kam_nav_active( 'home' ); ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                            Home
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link " href="<?php echo esc_url( home_url( '/#about' ) ); ?>">
                            About
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link "
                            href="<?php echo esc_url( home_url( '/#services' ) ); ?>">
                            Services
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link<?php echo kam_nav_active( 'contact' ); ?>"
                            href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">
                            Contact
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link<?php echo kam_nav_active( 'careers' ); ?>"
                            href="<?php echo esc_url( home_url( '/careers/' ) ); ?>">
                            Careers
                        </a>
                    </li>

                </ul>
            </div>
        </div>
    </nav>

<main>
