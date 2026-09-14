import Image from "next/image";

type BrandLogoProps = {
  variant?: "lockup" | "mark";
  tone?: "light" | "dark" | "auto";
};

export function BrandLogo({ variant = "lockup", tone = "auto" }: BrandLogoProps) {
  const image = (mode: "light" | "dark", auto = false) => <Image
    src={variant === "mark"
      ? mode === "dark" ? "/brand/clientflow-app-icon.svg" : "/brand/clientflow-mark.svg"
      : `/brand/clientflow-lockup-${mode}.svg`}
    alt="ClientFlow"
    loading="eager"
    width={variant === "mark" ? 64 : 308}
    height={64}
    className={`brand-logo brand-logo-${variant}${auto ? ` brand-logo-auto-${mode}` : ""}`}
  />;

  return tone === "auto" ? <>{image("light", true)}{image("dark", true)}</> : image(tone);
}
