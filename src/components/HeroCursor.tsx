"use client";
import { useEffect } from "react";

export default function HeroCursor() {
  useEffect(() => {
    let cx = 0, cy = 0, tx = 0, ty = 0;
    let raf = 0;
    const onMove = (e: MouseEvent) => {
      tx = (e.clientX / window.innerWidth - 0.5) * 2;
      ty = (e.clientY / window.innerHeight - 0.5) * 2;
    };
    const loop = () => {
      cx += (tx - cx) * 0.06;
      cy += (ty - cy) * 0.06;
      document.documentElement.style.setProperty("--bx", cx.toFixed(4));
      document.documentElement.style.setProperty("--by", cy.toFixed(4));
      raf = requestAnimationFrame(loop);
    };
    window.addEventListener("mousemove", onMove, { passive: true });
    raf = requestAnimationFrame(loop);
    return () => {
      window.removeEventListener("mousemove", onMove);
      cancelAnimationFrame(raf);
    };
  }, []);
  return null;
}
