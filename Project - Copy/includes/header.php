<?php if (empty($_SESSION['last_login'])): ?>
    <div class="banner success" id="loginBanner">
        🎉 Welcome! This is your first login.
    </div>
<?php else: ?>
    <div class="banner" id="loginBanner">
        Last login: <?= date("d M Y H:i", strtotime($_SESSION['last_login'])) ?>
    </div>
<?php endif; ?>

<style>
    #loginBanner {
        position: fixed;
        top: 20px;
        nav-left: 20px;
        padding: 14px 20px;
        border-radius: 8px;
        background: #ee422f;
        color: #fff;
        font-size: 14px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 9999;
        opacity: 1;
        transition: opacity 0.8s ease;
    }
    #loginBanner.success {
        background: #07c851;
    }
</style>

<script>
    setTimeout(() => {
        const banner = document.getElementById('loginBanner');
        banner.style.opacity = '0';
        setTimeout(() => banner.remove(), 800); // remove after fade
    }, 3000); // shows for 3 seconds
</script>