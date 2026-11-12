<footer class="pg-app-footer" role="contentinfo">
    <div class="pg-app-footer-bar">
        <nav class="pg-app-footer-links" aria-label="Footer">
            <a href="{{ route('home') }}">Home</a>
            @auth
                <a href="{{ route('dashboard') }}">Dashboard</a>
                <a href="{{ route('helpdesk.index') }}">Cases</a>
                <a href="{{ route('home') }}#about">About</a>
            @else
                <a href="{{ route('login') }}">Sign in</a>
                <a href="{{ route('home') }}#demo">Demo</a>
                <a href="{{ route('home') }}#about">About</a>
            @endauth
        </nav>
        <p class="pg-app-footer-copy">
            &copy; {{ now()->year }} Tanui Sila Kipngetich.
            PlantGrid is an independent educational portfolio project and is not affiliated with, endorsed by, or a product of any third-party CRM vendor. Sample data does not represent a real factory, company or network.
        </p>
    </div>
</footer>
