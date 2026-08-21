const ales = [
  { name: 'IPA', note: 'A crisp, hop-forward brewery favourite.' },
  { name: 'BS21', note: 'A locally brewed ale from Clevedon Brewery.' },
  { name: "Fools Gold", note: 'From Twisted Oak.' },
]

const events = ['Clevedon Show', 'Clevedon Brewery Makers & Crafters Market']

const openingTimes = [
  ['Thursday', '4pm – 7pm'],
  ['Friday', '4pm – 9pm'],
  ['Saturday', '2pm – 9pm'],
  ['Sunday', '2pm – 6pm'],
]

function App() {
  return (
    <main className="app-shell">
      <header className="hero">
        <div className="badge">CLEVEDON</div>
        <h1>Clevedon Brewery<br />&amp; Tap Bar</h1>
        <p>Good beer. Local people. A warm welcome.</p>
      </header>

      <nav className="quick-links" aria-label="Quick links">
        <a href="#ales">Our Ales</a>
        <a href="#events">What&apos;s On</a>
        <a href="#hours">Opening Times</a>
      </nav>

      <section id="ales" className="card">
        <div className="section-heading">
          <span>🍺</span>
          <div><p className="eyebrow">Freshly poured</p><h2>Current Ales</h2></div>
        </div>
        <div className="ale-list">
          {ales.map((ale) => (
            <article className="ale" key={ale.name}>
              <div className="ale-topline">
                <span className="ale-status">ON TAP</span>
                <span className="ale-mark">CLEVEDON BREWERY</span>
              </div>
              <h3>{ale.name}</h3>
              <p>{ale.note}</p>
            </article>
          ))}
        </div>
      </section>

      <section id="events" className="card">
        <div className="section-heading">
          <span>📅</span>
          <div><p className="eyebrow">Coming up</p><h2>What&apos;s On</h2></div>
        </div>
        <div className="event-list">
          {events.map((event) => (
            <article className="event" key={event}>
              <span className="event-label">UPCOMING</span>
              <strong>{event}</strong>
            </article>
          ))}
        </div>
      </section>

      <section id="hours" className="card">
        <div className="section-heading">
          <span>🕐</span>
          <div><p className="eyebrow">Come and see us</p><h2>Opening Times</h2></div>
        </div>
        <dl className="hours">
          {openingTimes.map(([day, time]) => <div key={day}><dt>{day}</dt><dd>{time}</dd></div>)}
        </dl>
      </section>

      <section className="welcome">
        <p className="eyebrow">Welcome to your local brewery</p>
        <h2>Drink local. Support local.</h2>
        <p>Keep this page handy for our current ales, upcoming events and opening times.</p>
      </section>

      <footer>Clevedon Brewery &amp; Tap Bar</footer>
    </main>
  )
}

export default App
