# 🎨 Custom CSS System - Kickerliga Management

Dieses Verzeichnis enthält benutzerdefinierte CSS-Styles, die automatisch in allen Templates geladen werden.

## 📁 Dateistruktur

```
kickLiga/public/assets/css/
├── custom.css          # Haupt-CSS-Datei (automatisch geladen)
├── README.md          # Diese Dokumentation
└── .htaccess          # Cache-Optimierung für statische Dateien
```

## 🚀 Verwendung

### Automatisches Laden
Die `custom.css` wird automatisch in allen Templates über das Layout geladen:
```html
<link rel="stylesheet" href="{{ base_url() }}/assets/css/custom.css">
```

### Verfügbare CSS-Klassen

#### 🎨 Design-Klassen
- `.custom-gradient` - Schöner Verlauf (blau-lila)
- `.custom-shadow` - Erhöhter Schatten-Effekt
- `.glass-effect` - Glasmorphismus-Effekt
- `.text-gradient` - Text mit Farbverlauf

#### 🃏 Card-Verbesserungen
- `.card-custom` - Hover-Animation für Karten
- `.dashboard-stat-card` - Spezielle Dashboard-Statistik-Karten

#### 🎯 Button-Styles
- `.btn-custom` - Individueller Button mit Hover-Effekten

#### 🏆 Achievement-Styles
- `.achievement-badge` - Goldene Achievement-Badges

#### 📊 ELO & Statistiken
- `.elo-rating-display` - Prominente ELO-Rating-Anzeige
- `.chart-container` - Verbesserte Chart-Container

#### 📋 Tabellen
- `.table-custom` - Verbesserte dunkle Tabellen

#### 🎮 Match-Ergebnisse
- `.match-result-win` - Grüner linker Rand für Siege
- `.match-result-loss` - Roter linker Rand für Niederlagen
- `.match-result-draw` - Grauer linker Rand für Unentschieden

#### ✨ Animationen
- `.fade-in-up` - Fade-In-Animation von unten
- `.pulse-on-hover` - Pulsieren beim Hover

#### 🎆 Spezialeffekte
- `.success-glow` - Grüner Glow-Effekt
- `.error-glow` - Roter Glow-Effekt

## 🛠️ CSS erweitern

### Neue Styles hinzufügen
Einfach die `custom.css` bearbeiten:

```css
/* Neue Klasse hinzufügen */
.meine-neue-klasse {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
    padding: 1rem;
    border-radius: 0.5rem;
}
```

### In Templates verwenden
```html
<div class="card card-custom glass-effect">
    <div class="card-body">
        <h5 class="text-gradient">Titel mit Farbverlauf</h5>
        <p>Inhalt der Karte</p>
        <button class="btn btn-custom pulse-on-hover">Custom Button</button>
    </div>
</div>
```

## 📱 Responsive Design

Das CSS enthält bereits mobile Anpassungen:

```css
@media (max-width: 768px) {
    .elo-rating-display {
        font-size: 1.25rem;
        padding: 0.75rem;
    }
}
```

## ⚡ Performance

- **Caching**: Assets werden 7 Tage gecacht
- **Kompression**: Automatische GZIP-Kompression
- **Optimierung**: Minimale CSS-Größe ohne Build-Tools

## 💡 Tipps

### 1. Klassen kombinieren
```html
<div class="card card-custom fade-in-up">
    <!-- Kombiniert Card-Hover mit Fade-Animation -->
</div>
```

### 2. CSS-Variablen nutzen
Das System nutzt CSS-Custom-Properties aus dem Layout:
```css
.meine-klasse {
    background-color: var(--bs-primary);
    color: var(--bs-body-color);
}
```

### 3. Bootstrap erweitern, nicht überschreiben
```css
/* Gut: Erweitert Bootstrap */
.btn-custom {
    /* Neue Styles */
}

/* Vermeiden: Überschreibt Bootstrap */
.btn-primary {
    /* Kann Probleme verursachen */
}
```

## 🎯 Beispiele

### Achievement-Badge
```html
<span class="achievement-badge">🏆 Torschützenkönig</span>
```

### ELO-Rating prominent anzeigen
```html
<div class="elo-rating-display">1337 ELO</div>
```

### Glowing Success Card
```html
<div class="card glass-effect success-glow">
    <div class="card-body">
        <h5 class="text-gradient">Erfolgreich!</h5>
    </div>
</div>
```

### Match-Ergebnis
```html
<div class="list-group-item match-result-win">
    Sieg gegen Max Mustermann
</div>
```

## 🔄 Updates

Da keine Build-Tools verwendet werden, sind Änderungen sofort sichtbar:
1. CSS-Datei bearbeiten
2. Seite neu laden
3. Fertig! ✨

Die Cache-Einstellungen sorgen dafür, dass Produktions-Performance optimal bleibt. 