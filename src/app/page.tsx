import Link from "next/link";
import Image from "next/image";
import ConsultForm from "@/components/ConsultForm";
import ServiceCard from "@/components/ServiceCard";
import FaqSection from "@/components/FaqSection";
import AnimatedCounter from "@/components/AnimatedCounter";
import HeroCursor from "@/components/HeroCursor";

const services = [
  { glyph: "₽", name: "Бухгалтерские услуги", desc: "Полное ведение учёта и отчётности: АУСН, УСН, ОСНО, зарплата и кадры.", slug: "bukhgalterskie-uslugi" },
  { glyph: "С", name: "Бухгалтерская страховка", desc: "Проверка отчётности, ответы налоговой и стратегические сессии.", slug: "strahovka" },
  { glyph: "А", name: "Аудит — чек-ап бизнеса", desc: "Проверка отчётности и процессов: риски, ошибки, точки оптимизации.", slug: "audit" },
  { glyph: "У", name: "Управленческий учёт", desc: "Себестоимость, P&L, ДДС — понимание, куда уходят деньги.", slug: "upravlencheskii-uchet" },
  { glyph: "М", name: "Бухгалтерия для маркетплейсов", desc: "Учёт и налоги для селлеров с учётом специфики площадок.", slug: "marketplace" },
  { glyph: "→", name: "Все услуги", desc: "Посмотрите полный список услуг и тарифов.", slug: null },
];

const advantages = [
  { num: "01", title: "Опыт и профессионализм", desc: "Более 20 лет успешной работы в сфере бухгалтерского обслуживания." },
  { num: "02", title: "Эксперты во многих направлениях", desc: "Бухгалтерский и управленческий учёт, юридическая помощь и многое другое — в одной команде." },
  { num: "03", title: "Индивидуальный подход", desc: "Разрабатываем индивидуальные решения, учитывающие специфику вашего бизнеса." },
  { num: "04", title: "Качество и надёжность", desc: "Гарантируем точность и своевременность всех бухгалтерских операций." },
];

const team = [
  { name: "Самылова Ирина", role: "Главный бухгалтер, аудитор", exp: "в компании более 20 лет", photo: "/team/bx_1740173516479.jpg" },
  { name: "Тимофеева Юлия", role: "Главный бухгалтер, аудитор, консультант", exp: "в компании 3 года", photo: "/team/MVV00348.jpg" },
  { name: "Анастасия Зайцева", role: "Специалист по управленческому учёту", exp: "работает более 3 лет", photo: "/team/meOsa7UsjTojpg.png" },
  { name: "Инна Пысларь", role: "Старший бухгалтер", exp: "", photo: "/team/MVV00359.jpg" },
];

const articles = [
  { href: "/blog/kak-nalogovaya-vidit-vash-biznes", photo: null, bg: "#1A1A1A", glyph: "ФНС", tag: "Налоговая", title: "Как налоговая видит ваш бизнес" },
  { href: "/blog/chto-delat-esli-prishlo-pismo-iz-fns", photo: "/articles/pismo-fns.png", bg: "#F07828", glyph: "✉", tag: "Налоговая", title: "Что делать, если пришло письмо из ФНС" },
  { href: "/blog/kuda-utekayut-dengi-v-biznese", photo: "/articles/dengi.jpg", bg: "#6B6B6B", glyph: "₽", tag: "Финансы", title: "Куда утекают деньги в бизнесе" },
];

const card = { background: "#fff", border: "1px solid #E8E8E8", borderRadius: 10, transition: "all 150ms ease" } as React.CSSProperties;

export default function HomePage() {
  return (
    <>
      <style>{`
        @keyframes blobDrift{0%,100%{transform:translate(0,0) scale(1)}33%{transform:translate(40px,-30px) scale(1.08)}66%{transform:translate(-30px,24px) scale(0.94)}}
        @keyframes blobDrift2{0%,100%{transform:translate(0,0) scale(1)}40%{transform:translate(-50px,36px) scale(1.12)}75%{transform:translate(26px,-20px) scale(0.9)}}
        @keyframes marqueeScroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
        @keyframes qzCardA{0%,40%{opacity:1;transform:translateY(0)}48%,90%{opacity:0;transform:translateY(-10px)}98%,100%{opacity:1;transform:translateY(0)}}
        @keyframes qzCardB{0%,42%{opacity:0;transform:translateY(12px)}50%,88%{opacity:1;transform:translateY(0)}96%,100%{opacity:0;transform:translateY(12px)}}
        @keyframes qzSelA{0%,14%{background:#fff;border-color:#E8E8E8}26%,100%{background:#FEF0E6;border-color:#F07828}}
        @keyframes qzSelB{0%,62%{background:#fff;border-color:#E8E8E8}74%,100%{background:#FEF0E6;border-color:#F07828}}
        @keyframes qzBarA{0%,18%{width:11%}30%,100%{width:22%}}
        @keyframes qzBarB{0%,64%{width:22%}78%,100%{width:33%}}
        @keyframes qzFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
        @keyframes markerIn{from{background-size:0% 0.3em}to{background-size:100% 0.3em}}
        @keyframes ctaPulse{0%,100%{box-shadow:0 0 0 0 rgba(240,120,40,0.45)}55%{box-shadow:0 0 0 10px rgba(240,120,40,0)}}
        .adv-row:hover{background:rgba(255,255,255,0.04)}
        .marker-anim{background:linear-gradient(#FEC89E,#FEC89E) no-repeat bottom left;background-size:0% 0.3em;animation:markerIn 0.9s 0.55s forwards;padding-bottom:0.05em;display:inline}
        .btn-pulse{animation:ctaPulse 2.6s ease-in-out infinite}
        .marquee-track:hover{animation-play-state:paused}
        .blob-a{transform:translate(calc(var(--bx,0)*30px),calc(var(--by,0)*20px));transition:transform 0.8s ease}
        .blob-b{transform:translate(calc(var(--bx,0)*-20px),calc(var(--by,0)*28px));transition:transform 0.8s ease}
      `}</style>

      {/* Hero */}
      <section style={{ position: "relative", overflow: "hidden" }}>
        <HeroCursor />
        <div className="blob-a" style={{ position: "absolute", top: -120, right: -80, pointerEvents: "none" }}>
          <div style={{ width: 520, height: 520, borderRadius: "50%", background: "radial-gradient(circle at 40% 40%, rgba(240,120,40,0.3), transparent 65%)", animation: "blobDrift 14s ease-in-out infinite" }} />
        </div>
        <div className="blob-b" style={{ position: "absolute", bottom: -140, left: -100, pointerEvents: "none" }}>
          <div style={{ width: 460, height: 460, borderRadius: "50%", background: "radial-gradient(circle at 60% 50%, rgba(254,180,120,0.5), transparent 65%)", animation: "blobDrift2 18s ease-in-out infinite" }} />
        </div>
        <div style={{ maxWidth: 1100, margin: "0 auto", padding: "88px 24px 0", position: "relative" }}>
        <div style={{ fontSize: 13, fontWeight: 600, letterSpacing: "0.12em", textTransform: "uppercase", color: "#F07828", marginBottom: 24 }}>
          Комплексное бухгалтерское обслуживание · Москва
        </div>
        <h1 style={{ margin: "0 0 24px", fontSize: "clamp(44px,6vw,72px)", lineHeight: 1.05, fontWeight: 900, letterSpacing: "-0.03em", maxWidth: 780 }}>
          Ваш бизнес —{" "}
          <span className="marker-anim">наша зона</span>{" "}
          ответственности
        </h1>
        <p style={{ margin: "0 0 36px", fontSize: 18, lineHeight: 1.65, color: "#6B6B6B", maxWidth: 540 }}>
          Настроим и укомплектуем вашу бухгалтерию профессиональными специалистами. Работаем с ИП и ООО удалённо по всей России.
        </p>
        <div style={{ display: "flex", gap: 20, flexWrap: "wrap", alignItems: "center" }}>
          <Link href="/calculator" className="btn-primary btn-pulse" style={{ height: 52, padding: "0 28px", fontSize: 16 }}>
            Рассчитать стоимость
          </Link>
          <Link href="#zapis" style={{ fontSize: 15, fontWeight: 600, color: "#1A1A1A", textDecoration: "underline", textUnderlineOffset: 4 }}>
            Записаться на консультацию →
          </Link>
        </div>

        {/* Stats grid */}
        <div className="m-stats" style={{ display: "grid", gridTemplateColumns: "repeat(4,1fr)", gap: 0, marginTop: 56 }}>
          <div style={{ padding: "30px 28px 34px 0", borderRight: "1px solid #E8E8E8" }}>
            <div style={{ fontSize: 44, fontWeight: 900, letterSpacing: "-0.02em", color: "#F07828", lineHeight: 1 }}><AnimatedCounter target={20} suffix="+" /></div>
            <div style={{ fontSize: 14, color: "#6B6B6B", marginTop: 6 }}>лет на рынке</div>
          </div>
          <div style={{ padding: "30px 28px 34px" }}>
            <div style={{ fontSize: 44, fontWeight: 900, letterSpacing: "-0.02em", color: "#1A1A1A", lineHeight: 1 }}><AnimatedCounter target={350} suffix="+" /></div>
            <div style={{ fontSize: 14, color: "#6B6B6B", marginTop: 6 }}>клиентов на сопровождении</div>
          </div>
          <div className="m-hide" style={{ padding: "30px 28px 34px", borderLeft: "1px solid #E8E8E8", borderRight: "1px solid #E8E8E8" }}>
            <div style={{ fontSize: 44, fontWeight: 900, letterSpacing: "-0.02em", color: "#1A1A1A", lineHeight: 1 }}>2 часа</div>
            <div style={{ fontSize: 14, color: "#6B6B6B", marginTop: 6 }}>максимум до ответа</div>
          </div>
          <div style={{ padding: "30px 0 34px 28px" }}>
            <div style={{ fontSize: 44, fontWeight: 900, letterSpacing: "-0.02em", color: "#1A1A1A", lineHeight: 1 }}>0 ₽</div>
            <div style={{ fontSize: 14, color: "#6B6B6B", marginTop: 6 }}>штрафов по нашей вине</div>
          </div>
        </div>
        </div>
      </section>

      {/* Trust bar — marquee */}
      <section style={{ background: "#F5F5F5", borderTop: "1px solid #E8E8E8", borderBottom: "1px solid #E8E8E8", overflow: "hidden" }}>
        <div style={{ padding: "22px 0", fontSize: 14, color: "#3D3D3D", fontWeight: 500 }}>
          <div className="marquee-track" style={{ display: "flex", gap: 48, animation: "marqueeScroll 22s linear infinite", width: "max-content" }}>
            {["1С:Партнёр", "Страхование профответственности", "УСН · ОСНО · АУСН · Патент", "НДС и ВЭД", "Работаем удалённо по всей России", "Более 20 лет опыта",
              "1С:Партнёр", "Страхование профответственности", "УСН · ОСНО · АУСН · Патент", "НДС и ВЭД", "Работаем удалённо по всей России", "Более 20 лет опыта"].map((t, i) => (
              <span key={i} style={{ whiteSpace: "nowrap", paddingLeft: i === 0 ? 24 : 0 }}>{t}</span>
            ))}
          </div>
        </div>
      </section>

      {/* Services */}
      <section id="uslugi" style={{ maxWidth: 1100, margin: "0 auto", padding: "80px 24px" }}>
        <div style={{ display: "flex", alignItems: "baseline", justifyContent: "space-between", gap: 16, marginBottom: 12 }}>
          <h2 style={{ margin: 0, fontSize: 36, fontWeight: 800, letterSpacing: "-0.02em" }}>Что мы делаем</h2>
          <Link href="/uslugi" style={{ fontWeight: 600, fontSize: 15, color: "#F07828" }}>Все услуги →</Link>
        </div>
        <p style={{ margin: "0 0 36px", fontSize: 16, color: "#6B6B6B", maxWidth: 560 }}>Мы работаем более 20 лет и знаем, что нужно бизнесу на каждом этапе.</p>
        <div className="m-1col t-2col" style={{ display: "grid", gridTemplateColumns: "repeat(3,1fr)", gap: 24 }}>
          {services.map(s => (
            <ServiceCard key={s.name} {...s} />
          ))}
        </div>
      </section>

      {/* Advantages */}
      <section style={{ background: "#3D3D3D", color: "#fff" }}>
        <div style={{ maxWidth: 1100, margin: "0 auto", padding: "72px 24px" }}>
          <h2 style={{ margin: "0 0 12px", fontSize: 36, fontWeight: 800, letterSpacing: "-0.02em" }}>Почему выбирают нас</h2>
          <p style={{ margin: "0 0 40px", fontSize: 16, lineHeight: 1.6, color: "rgba(255,255,255,0.6)", maxWidth: 560 }}>Наш профессионализм и индивидуальный подход делают нас идеальным выбором для бухгалтерского обслуживания.</p>
          <div style={{ borderTop: "1px solid rgba(255,255,255,0.15)" }}>
            {advantages.map((a, i) => (
              <div key={a.num} className="m-adv-row adv-row" style={{
                display: "grid", gridTemplateColumns: "96px 1fr 1fr", gap: "0 48px",
                padding: "28px 0", borderBottom: "1px solid rgba(255,255,255,0.15)",
                alignItems: "center", transition: "background 150ms ease",
              }}>
                <div style={{ fontSize: 56, fontWeight: 900, lineHeight: 1, color: "transparent", WebkitTextStroke: "1.5px rgba(240,120,40,0.85)" }}>{a.num}</div>
                <div style={{ fontSize: 22, fontWeight: 700, letterSpacing: "-0.01em" }}>{a.title}</div>
                <div style={{ fontSize: 15, lineHeight: 1.6, color: "rgba(255,255,255,0.6)" }}>{a.desc}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Galina quote */}
      <section style={{ background: "#F07828", color: "#fff" }}>
        <div style={{ maxWidth: 1100, margin: "0 auto", padding: "72px 24px", display: "grid", gridTemplateColumns: "1fr 1.5fr", gap: 64, alignItems: "center" }} className="m-galina">
          <div style={{ position: "relative", height: 340, borderRadius: 12, overflow: "hidden", background: "rgba(255,255,255,0.15)" }}>
            <div style={{ position: "absolute", inset: 0, display: "flex", alignItems: "center", justifyContent: "center", flexDirection: "column", gap: 8 }}>
              <div style={{ width: 64, height: 64, borderRadius: "50%", background: "rgba(255,255,255,0.3)", display: "flex", alignItems: "center", justifyContent: "center", fontSize: 28, fontWeight: 700 }}>Г</div>
              <div style={{ fontSize: 14, fontWeight: 600, opacity: 0.8 }}>Горелкина Галина Викторовна</div>
              <div style={{ fontSize: 12, opacity: 0.6 }}>Руководитель компании</div>
            </div>
          </div>
          <div>
            <div style={{ fontSize: 88, fontWeight: 900, lineHeight: 0.5, marginBottom: 6, opacity: 0.35 }}>«</div>
            <p style={{ margin: "0 0 28px", fontSize: 28, lineHeight: 1.32, fontWeight: 700, letterSpacing: "-0.015em" }}>
              Мы несём полную ответственность за ваш учёт перед налоговой службой. Правильно организованный учёт — залог спокойствия и уверенности руководителя.
            </p>
            <a href="https://t.me/Galina_Gor" target="_blank" rel="noopener noreferrer"
              style={{ display: "inline-flex", alignItems: "center", gap: 10, padding: "12px 22px", background: "rgba(255,255,255,0.2)", borderRadius: 8, color: "#fff", fontWeight: 600, fontSize: 15, textDecoration: "none" }}>
              <span style={{ fontSize: 18 }}>✈</span>
              Написать мне лично в Telegram
            </a>
          </div>
        </div>
      </section>

      {/* Calculator CTA */}
      <section id="calculator" style={{ maxWidth: 1100, margin: "0 auto", padding: "80px 24px" }}>
        <div className="m-1col" style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 56, alignItems: "center" }}>
          <div>
            <div style={{ fontSize: 13, fontWeight: 600, letterSpacing: "0.12em", textTransform: "uppercase", color: "#F07828", marginBottom: 18 }}>Калькулятор услуг</div>
            <h2 style={{ margin: "0 0 16px", fontSize: 36, fontWeight: 800, letterSpacing: "-0.02em", lineHeight: 1.15 }}>Подберём услуги под вашу ситуацию</h2>
            <p style={{ margin: "0 0 32px", fontSize: 17, color: "#6B6B6B", lineHeight: 1.6, maxWidth: 440 }}>9 коротких вопросов — и вы узнаете, какое обслуживание подходит именно вашему бизнесу. Без звонков и почты.</p>
            <div style={{ display: "flex", flexDirection: "column", gap: 16, marginBottom: 36 }}>
              {[
                ["01", "Ответьте на вопросы о вашем бизнесе"],
                ["02", "Получите список подходящих услуг"],
                ["03", "Обсудите точный расчёт с бухгалтером"],
              ].map(([n, t]) => (
                <div key={n} style={{ display: "flex", gap: 16, alignItems: "baseline" }}>
                  <span style={{ fontSize: 22, fontWeight: 900, color: "#F07828", flexShrink: 0, width: 36 }}>{n}</span>
                  <span style={{ fontSize: 16, fontWeight: 500, color: "#3D3D3D" }}>{t}</span>
                </div>
              ))}
            </div>
            <Link href="/calculator" className="btn-primary" style={{ height: 54, padding: "0 30px", fontSize: 16 }}>Начать расчёт →</Link>
          </div>
          {/* Animated quiz preview */}
          <div className="m-hide-mobile" style={{ background: "#F07828", borderRadius: 14, padding: "44px 40px", overflow: "visible" }}>
            <div style={{ animation: "qzFloat 8s ease-in-out infinite" }}>
              <div style={{ position: "relative", height: 300 }}>
                {/* Card A */}
                <div style={{ position: "absolute", inset: 0, background: "#fff", borderRadius: 12, padding: 28, boxShadow: "0 20px 50px rgba(0,0,0,0.22)", animation: "qzCardA 8s ease-in-out infinite", boxSizing: "border-box" }}>
                  <div style={{ display: "flex", justifyContent: "space-between", fontSize: 12, color: "#6B6B6B", fontWeight: 600, marginBottom: 10 }}>
                    <span>Шаг 1 из 9</span><span style={{ color: "#F07828" }}>11%</span>
                  </div>
                  <div style={{ height: 6, background: "#F5F5F5", borderRadius: 3, marginBottom: 22, overflow: "hidden" }}>
                    <div style={{ height: "100%", background: "#F07828", borderRadius: 3, animation: "qzBarA 8s ease-in-out infinite" }} />
                  </div>
                  <div style={{ fontSize: 20, fontWeight: 700, marginBottom: 18, letterSpacing: "-0.01em" }}>Какая у вас форма собственности?</div>
                  <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                    <div style={{ border: "1.5px solid #E8E8E8", borderRadius: 10, padding: "16px 18px", fontSize: 16, fontWeight: 600, background: "#fff" }}>ИП</div>
                    <div style={{ border: "1.5px solid #E8E8E8", borderRadius: 10, padding: "16px 18px", fontSize: 16, fontWeight: 600, animation: "qzSelA 8s ease-in-out infinite" }}>ООО</div>
                  </div>
                </div>
                {/* Card B */}
                <div style={{ position: "absolute", inset: 0, background: "#fff", borderRadius: 12, padding: 28, boxShadow: "0 20px 50px rgba(0,0,0,0.22)", animation: "qzCardB 8s ease-in-out infinite", opacity: 0, boxSizing: "border-box" }}>
                  <div style={{ display: "flex", justifyContent: "space-between", fontSize: 12, color: "#6B6B6B", fontWeight: 600, marginBottom: 10 }}>
                    <span>Шаг 2 из 9</span><span style={{ color: "#F07828" }}>22%</span>
                  </div>
                  <div style={{ height: 6, background: "#F5F5F5", borderRadius: 3, marginBottom: 22, overflow: "hidden" }}>
                    <div style={{ height: "100%", background: "#F07828", borderRadius: 3, animation: "qzBarB 8s ease-in-out infinite" }} />
                  </div>
                  <div style={{ fontSize: 20, fontWeight: 700, marginBottom: 18, letterSpacing: "-0.01em" }}>Сколько у вас сотрудников?</div>
                  <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                    {["Нет", "1–5", "6–15", "16 и больше"].map((o, i) => (
                      <div key={o} style={{ border: "1.5px solid #E8E8E8", borderRadius: 10, padding: "14px 18px", fontSize: 15, fontWeight: 600, ...(i === 1 ? { animation: "qzSelB 8s ease-in-out infinite" } : { background: "#fff" }) }}>{o}</div>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Team */}
      <section id="team" style={{ maxWidth: 1100, margin: "0 auto", padding: "80px 24px" }}>
        <div style={{ display: "flex", alignItems: "baseline", justifyContent: "space-between", gap: 16, marginBottom: 36 }}>
          <h2 style={{ margin: 0, fontSize: 36, fontWeight: 800, letterSpacing: "-0.02em" }}>Ваши бухгалтеры</h2>
          <Link href="/team" style={{ fontWeight: 600, fontSize: 15, color: "#F07828" }}>Вся команда →</Link>
        </div>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 24, alignItems: "start" }}>
          <div style={{ position: "relative", height: 380, borderRadius: 12, overflow: "hidden" }}>
            <Image src="/team/team-group.jpg" alt="Команда Доверительной Бухгалтерии" fill style={{ objectFit: "cover", objectPosition: "center" }} />
          </div>
          <div className="m-1col" style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16 }}>
            {team.map(m => (
              <div key={m.name} style={{ ...card, overflow: "hidden" }}>
                <div style={{ height: 160, position: "relative" }}>
                  <Image src={m.photo} alt={m.name} fill style={{ objectFit: "cover", objectPosition: "top" }} />
                </div>
                <div style={{ padding: "14px 16px" }}>
                  <div style={{ fontSize: 14, fontWeight: 600 }}>{m.name}</div>
                  <div style={{ fontSize: 12, color: "#6B6B6B", margin: "3px 0 6px" }}>{m.role}</div>
                  {m.exp && <div style={{ fontSize: 11, color: "#F07828", fontWeight: 600 }}>{m.exp}</div>}
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Reviews */}
      <section style={{ background: "#F5F5F5", borderTop: "1px solid #E8E8E8", borderBottom: "1px solid #E8E8E8" }}>
        <div style={{ maxWidth: 1100, margin: "0 auto", padding: "80px 24px" }}>
          <div style={{ display: "flex", alignItems: "baseline", justifyContent: "space-between", gap: 16, marginBottom: 36 }}>
            <h2 style={{ margin: 0, fontSize: 36, fontWeight: 800, letterSpacing: "-0.02em" }}>Что говорят клиенты</h2>
            <a href="https://yandex.ru/maps/org/doveritelnaya_bukhgalteriya/162739002915/reviews/" target="_blank" rel="noopener noreferrer" style={{ fontWeight: 600, fontSize: 15, color: "#F07828" }}>Все отзывы →</a>
          </div>
          <div className="m-1col t-2col" style={{ display: "grid", gridTemplateColumns: "repeat(3,1fr)", gap: 20 }}>
            {[
              { name: "Роман Б.", date: "15 мая 2025", text: "Обращался в «Доверительную бухгалтерию» за разовой консультацией по налогам в сфере коммерческой недвижимости — нужно было подготовить ответ в ФНС на претензию. Галина и Юлия детально разобрали ситуацию, подсветили риски и помогли сформулировать грамотный и аргументированный ответ." },
              { name: "Максим Отт", date: "6 февраля 2025", text: "Вчера провели консультацию с Галиной, которая длилась около двух часов. Она подробно ответила на все наши вопросы, касающиеся налогового, бухгалтерского и управленческого учёта. Галина проявила высокий профессионализм и глубокие знания в своей области." },
              { name: "Ирина Б.", date: "15 мая 2025", text: "Спасибо Галине и команде за профессионализм и поддержку. Обратилась за консультацией для подготовки ответа в ФНС. После изучения ситуации и документов оперативно получила ответ и общее понимание как мне следует поступить. Рекомендую!" },
              { name: "Скульптор Коваль", date: "22 июля 2025", text: "Огромное спасибо! Я думала никогда не разберусь в своей ситуации. К трём бухгалтерам обратилась — голова уже закипала. Здесь девочки за десять минут поняли где произошёл сбой и всё объяснили. Профессионалы!" },
              { name: "Владислав П.", date: "9 февраля 2025", text: "Потрясающая компания, грамотные специалисты, провели полный аудит, подсветили все слабые стороны, помогли выстроить всю работу. Однозначно рекомендую!" },
              { name: "Кирилл", date: "2 апреля 2025", text: "Спасибо Галине, как всегда всё чётко и по существу. Получил больше, чем планировал. Будем долго и плотно сотрудничать." },
            ].map(r => (
              <div key={r.name + r.date} style={{ ...card, padding: "22px 22px 20px", display: "flex", flexDirection: "column", gap: 12 }}>
                <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
                  <div style={{ width: 40, height: 40, borderRadius: "50%", background: "#3D3D3D", color: "#fff", fontWeight: 700, fontSize: 16, display: "flex", alignItems: "center", justifyContent: "center", flexShrink: 0 }}>{r.name[0]}</div>
                  <div>
                    <div style={{ fontSize: 14, fontWeight: 700 }}>{r.name}</div>
                    <div style={{ fontSize: 12, color: "#9E9A94" }}>{r.date}</div>
                  </div>
                  <div style={{ marginLeft: "auto", fontSize: 11, color: "#F07828", fontWeight: 600, background: "#FEF0E6", padding: "3px 8px", borderRadius: 4 }}>Яндекс</div>
                </div>
                <div style={{ color: "#F5A623", fontSize: 15, letterSpacing: 1 }}>★★★★★</div>
                <p style={{ margin: 0, fontSize: 14, lineHeight: 1.65, color: "#3D3D3D" }}>{r.text}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Articles */}
      <section>
        <div style={{ maxWidth: 1100, margin: "0 auto", padding: "80px 24px" }}>
          <div style={{ display: "flex", alignItems: "baseline", justifyContent: "space-between", gap: 16, marginBottom: 36 }}>
            <h2 style={{ margin: 0, fontSize: 36, fontWeight: 800, letterSpacing: "-0.02em" }}>Полезные материалы</h2>
            <Link href="/blog" style={{ fontWeight: 600, fontSize: 15, color: "#F07828" }}>Все статьи →</Link>
          </div>
          <div className="m-1col t-2col" style={{ display: "grid", gridTemplateColumns: "repeat(3,1fr)", gap: 24 }}>
            {articles.map(a => (
              <Link key={a.title} href={a.href}
                style={{ ...card, textDecoration: "none", color: "#1A1A1A", display: "block" }}>
                <div style={{ height: 160, background: a.bg, position: "relative", overflow: "hidden", display: "flex", alignItems: "center", justifyContent: "center" }}>
                  {a.photo
                    ? <Image src={a.photo} alt={a.title} fill style={{ objectFit: "cover" }} />
                    : <span style={{ color: "#fff", fontSize: 34, fontWeight: 800 }}>{a.glyph}</span>
                  }
                </div>
                <div style={{ padding: "18px 20px" }}>
                  <span style={{ display: "inline-block", background: "#FEF0E6", color: "#B84F1A", fontSize: 11, fontWeight: 600, padding: "3px 8px", borderRadius: 4, marginBottom: 10 }}>{a.tag}</span>
                  <div style={{ fontSize: 16, fontWeight: 600, lineHeight: 1.4, marginBottom: 8 }}>{a.title}</div>
                  <div style={{ fontSize: 12, color: "#F07828", fontWeight: 600 }}>Читать →</div>
                </div>
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* FAQ */}
      <section style={{ background: "#F5F5F5", borderTop: "1px solid #E8E8E8" }}>
        <FaqSection />
      </section>

      {/* Consultation form */}
      <section id="zapis" style={{ background: "#FEF0E6", borderTop: "1px solid #F5C9A0" }}>
        <div style={{ maxWidth: 1100, margin: "0 auto", padding: "80px 24px" }}>
          <div className="m-1col" style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 64, alignItems: "center" }}>
            <div>
              <div style={{ fontSize: 12, fontWeight: 600, letterSpacing: "0.1em", textTransform: "uppercase", color: "#F07828", marginBottom: 16 }}>Бесплатная консультация</div>
              <h2 style={{ margin: "0 0 20px", fontSize: 38, fontWeight: 800, letterSpacing: "-0.02em", lineHeight: 1.15 }}>Разберём вашу ситуацию и предложим решение</h2>
              <p style={{ margin: "0 0 32px", fontSize: 16, lineHeight: 1.7, color: "#3D3D3D" }}>Первая консультация бесплатная. Обсудим ваш бизнес, ответим на вопросы и скажем, чем можем помочь.</p>
              <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
                <a href="https://t.me/Galina_Gor" target="_blank" rel="noopener noreferrer" style={{ display: "flex", alignItems: "center", gap: 12, fontSize: 15, fontWeight: 600, color: "#1A1A1A" }}>
                  <span style={{ width: 40, height: 40, background: "#fff", borderRadius: 10, border: "1px solid #E8E8E8", display: "flex", alignItems: "center", justifyContent: "center", fontSize: 20 }}>✈</span>
                  Написать в Telegram
                </a>
                <a href="https://wa.me/79169179274" target="_blank" rel="noopener noreferrer" style={{ display: "flex", alignItems: "center", gap: 12, fontSize: 15, fontWeight: 600, color: "#1A1A1A" }}>
                  <span style={{ width: 40, height: 40, background: "#fff", borderRadius: 10, border: "1px solid #E8E8E8", display: "flex", alignItems: "center", justifyContent: "center", fontSize: 20 }}>💬</span>
                  Написать в WhatsApp
                </a>
                <a href="tel:+74957788168" style={{ display: "flex", alignItems: "center", gap: 12, fontSize: 15, fontWeight: 600, color: "#1A1A1A" }}>
                  <span style={{ width: 40, height: 40, background: "#fff", borderRadius: 10, border: "1px solid #E8E8E8", display: "flex", alignItems: "center", justifyContent: "center", fontSize: 20 }}>📞</span>
                  +7 (495) 778-81-68
                </a>
              </div>
            </div>
            <div style={{ background: "#fff", borderRadius: 16, padding: "36px 36px", boxShadow: "0 4px 32px rgba(240,120,40,0.08)", border: "1px solid #F5C9A0" }}>
              <div style={{ fontSize: 20, fontWeight: 700, marginBottom: 6 }}>Оставьте заявку</div>
              <div style={{ fontSize: 14, color: "#6B6B6B", marginBottom: 24 }}>Перезвоним в течение рабочего дня</div>
              <ConsultForm />
            </div>
          </div>
        </div>
      </section>
    </>
  );
}
