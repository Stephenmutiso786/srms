import "./globals.css";

export const metadata = {
  title: "Elimu Hub Frontend",
  description: "Vercel-ready frontend for Elimu Hub"
};

export default function RootLayout({ children }) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
