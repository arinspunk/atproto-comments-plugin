<?php
// Temporal: comprueba que la CI se pone en rojo. Se revierte en el siguiente commit.
class ZZ_CI_Red_Test extends WP_UnitTestCase {
        public function test_ci_goes_red() {
                $this->assertTrue( false );
        }
}
