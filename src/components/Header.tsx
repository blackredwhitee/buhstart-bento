"use client";
import { useState, useEffect } from "react";
import Link from "next/link";
import Image from "next/image";

const nav = [
  { label: "Услуги", href: "/uslugi" },
  { label: "Калькулятор", href: "/calculator" },
  { label: "Команда", href: "/team" },
  { label: "Статьи", href: "/blog" },
  { label: "Новости", href: "/novosti" },
  { label: "Контакты", href: "/contacts" },
];

export default function Header() {
  const [menuOpen, setMenuOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const [showBar, setShowBar] = useState(false);

  useEffect(() => {
    const onScroll = () => {
      setScrolled(window.scrollY > 10);
      setShowBar(window.scrollY > 200);
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  useEffect(() => {
    document.body.style.overflow = menuOpen ? "hidden" : "";
    return () => { document.body.style.overflow = ""; };
  }, [menuOpen]);

  return (
    <header style={{
      position: "sticky", top: 0, zIndex: 100, height: 72,
      background: scrolled ? "rgba(255,255,255,0.96)" : "#fff",
      backdropFilter: scrolled ? "blur(8px)" : "none",
      borderBottom: "1px solid #E8E8E8",
      transition: "background 200ms, box-shadow 200ms",
      boxShadow: scrolled ? "0 1px 8px rgba(0,0,0,0.06)" : "none",
    }}>
      <div className="container" style={{ display: "flex", alignItems: "center", height: "100%" }}>
        <Link href="/" style={{ display: "flex", alignItems: "center", marginRight: "auto", gap: 10 }}>
          <Image src="/logo.png" alt="Доверительная Бухгалтерия" width={200} height={56} style={{ height: 56, width: "auto", objectFit: "contain" }} priority />
        </Link>

        <nav style={{ display: "flex", gap: 28, alignItems: "center" }} className="d-hide-mobile">
          {nav.map(n => (
            <Link key={n.href} href={n.href} style={{ fontSize: 15, fontWeight: 500, color: "#3D3D3D" }}
              onMouseEnter={e => (e.currentTarget.style.color = "#F07828")}
              onMouseLeave={e => (e.currentTarget.style.color = "#3D3D3D")}>
              {n.label}
            </Link>
          ))}
          <a href="tel:+74957788168" style={{ marginLeft: 8, fontSize: 15, fontWeight: 700, color: "#1A1A1A" }}>
            +7 (495) 778-81-68
          </a>
          <Link href="/calculator" className="btn-primary" style={{ height: 40, padding: "0 18px", fontSize: 14 }}>
            Записаться
          </Link>
        </nav>

        <button onClick={() => setMenuOpen(!menuOpen)} aria-label="Меню"
          style={{ display: "none", width: 44, height: 44, background: "none", border: "none", cursor: "pointer", flexDirection: "column", justifyContent: "center", gap: 5 }}
          className="burger-btn">
          <span style={{ display: "block", height: 2, width: 24, background: "#1A1A1A", transition: "transform 200ms", transform: menuOpen ? "rotate(45deg) translate(5px,5px)" : "none" }} />
          <span style={{ display: "block", height: 2, width: 24, background: "#1A1A1A", opacity: menuOpen ? 0 : 1, transition: "opacity 200ms" }} />
          <span style={{ display: "block", height: 2, width: 24, background: "#1A1A1A", transition: "transform 200ms", transform: menuOpen ? "rotate(-45deg) translate(5px,-5px)" : "none" }} />
        </button>
      </div>

      {menuOpen && (
        <div style={{ position: "fixed", inset: 0, top: 72, background: "#fff", zIndex: 99, padding: "24px 24px 80px", overflowY: "auto" }}>
          {nav.map(n => (
            <Link key={n.href} href={n.href} onClick={() => setMenuOpen(false)}
              style={{ display: "block", padding: "16px 0", fontSize: 20, fontWeight: 600, borderBottom: "1px solid #E8E8E8", color: "#1A1A1A" }}>
              {n.label}
            </Link>
          ))}
          <a href="tel:+74957788168" style={{ display: "block", marginTop: 24, fontSize: 22, fontWeight: 700, color: "#F07828" }}>
            +7 (495) 778-81-68
          </a>
          <Link href="/calculator" onClick={() => setMenuOpen(false)}
            style={{ display: "block", marginTop: 16, textAlign: "center", padding: "14px 24px", background: "#F07828", color: "#fff", borderRadius: 6, fontWeight: 600, fontSize: 16 }}>
            Записаться на консультацию
          </Link>
        </div>
      )}

      <style>{`
        @keyframes slideDown{from{transform:translateY(-100%);opacity:0}to{transform:translateY(0);opacity:1}}
        header{animation:slideDown 0.4s ease both}
        @media (max-width: 767px) {
          .d-hide-mobile { display: none !important; }
          .burger-btn { display: flex !important; }
        }
        @media (min-width: 768px) {
          .mobile-bar { display: none !important; }
        }
      `}</style>

      {/* Mobile bottom bar — appears after 200px scroll */}
      {showBar && !menuOpen && (
        <div className="mobile-bar" style={{ position: "fixed", left: 0, right: 0, bottom: 0, zIndex: 150, height: 56, background: "#fff", borderTop: "1px solid #E8E8E8", display: "flex", gap: 10, padding: "6px 12px", boxSizing: "border-box" }}>
          <Link href="/contacts#form" style={{ flex: 1, textDecoration: "none", background: "#F07828", color: "#fff", fontWeight: 600, fontSize: 15, borderRadius: 6, display: "flex", alignItems: "center", justifyContent: "center" }}>
            Записаться
          </Link>
          <a href="tel:+74957788168" aria-label="Позвонить" style={{ width: 44, flexShrink: 0, textDecoration: "none", border: "1.5px solid #3D3D3D", borderRadius: 6, display: "flex", alignItems: "center", justifyContent: "center" }}>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z" stroke="#3D3D3D" strokeWidth="2" strokeLinejoin="round"/></svg>
          </a>
        </div>
      )}
    </header>
  );
}
