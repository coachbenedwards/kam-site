</main>
<section class="container-fluid hero text-white">
   <div class="container">
      <div class="row gy-4">

         <!-- ================= LEFT : BRANCHES ================= -->
         <div class="col-lg-7">
            <h4 class="branch-title mb-4 text-white">BRANCHES</h4>

            <div class="row">
               <?php
               $kam_b = function_exists( 'kam_branches' ) ? kam_branches() : array();
               $kam_cols = array_chunk( $kam_b, 8, true );
               foreach ( $kam_cols as $kam_col ) : ?>
               <div class="col-md-6">
                  <?php foreach ( $kam_col as $kam_slug => $kam_row ) : ?>
                  <p><i class="fa-solid fa-location-dot text-white"></i>
                     <a href="<?php echo esc_url( home_url( '/' . $kam_slug . '/' ) ); ?>" class="text-white text-decoration-none ms-2">
                        <?php echo esc_html( $kam_row['name'] ); ?>
                     </a>
                  </p>
                  <?php endforeach; ?>
               </div>
               <?php endforeach; ?>
            </div>
         </div>

         <!-- ================= RIGHT : SERVICES ================= -->
         <div class="col-lg-5">
            <h4 class="branch-title mb-4 text-white">SERVICES</h4>
            <!-- services-box -->
            <div class="">
               <div class="row">
                  <div class="col-md-4 d-none d-md-block"></div>
                  <div class="col-md-6">
                     <p><i class="fa-solid fa-wrench text-white"></i>
                        <a href="https://kamservicing.com/bookings.php" class="text-white text-decoration-none ms-2">Book an MOT</a>
                     </p>
                     <p><i class="fa-solid fa-wrench text-white"></i>
                        <a href="https://kamservicing.com/bookings.php" class="text-white text-decoration-none ms-2">Book a Service</a>
                     </p>
                     <p><i class="fa-solid fa-wrench text-white"></i>
                        <a href="https://kamservicing.com/tyres.php" class="text-white text-decoration-none ms-2">Buy Tyres</a>
                     </p>
                     <p><i class="fa-solid fa-wrench text-white"></i>
                        <a href="https://kamservicing.com/bookings.php" class="text-white text-decoration-none ms-2">Book A/C Regas</a>
                     </p>
                     <p><i class="fa-solid fa-magnifying-glass text-white"></i>
                           <a href="<?php echo esc_url( home_url( '/services/' ) ); ?>" class="text-white text-decoration-none ms-2">View All Services</a>
                     </p>
                  </div>
               </div>
            </div>

         </div>
      </div>
   </div>
</section>

<footer class="text-center py-4 bg-light">
   <div class="container">
      <p class="mb-0">
         &copy; KAM Servicing - <?php echo esc_html( gmdate( 'Y' ) ); ?> |
         <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>" class="privacy-policy">Privacy Policy</a>|
         <a href="<?php echo esc_url( home_url( '/services/' ) ); ?>" class="all-services">All Services</a>|
         <a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>" class="terms-and-conditions">Terms & Conditions</a>
      </p>
   </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
