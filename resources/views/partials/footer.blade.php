<footer class="pg-sf-footer" role="contentinfo">
    <div class="pg-sf-footer-bar">
        <nav class="pg-sf-footer-links" aria-label="Footer">
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
        <p class="pg-sf-footer-copy">
            &copy; {{ now()->year }} Tanui Sila Kipngetich.
            PlantGrid is a portfolio demonstration. Sample data does not represent a real factory, company or network.
        </p>
    </div>
</footer>
